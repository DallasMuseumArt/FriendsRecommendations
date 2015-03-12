<?php namespace DMA\Recommendations\Classes\Backends;

use DB;
use Log;
use Event;

use Elasticsearch;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\Curl\CouldNotConnectToHost;

use DMA\Recommendations\Models\Settings;
use DMA\Recommendations\Classes\Backends\BackendBase;
use DMA\Recommendations\Classes\RecomendationManager;

use Illuminate\Support\Collection;
use GuzzleHttp\json_decode;


/**
 * ElasticSearch recomendation engine backend
 *
 * @package DMA\Recomendations\Classes\Backends
 * @author Carlos Arroyo, Kristen Arnold
 */
class ElasticSearchBackend extends BackendBase
{
        
    /**
     * @var DMA\Recommendations\Classes\RecomendationManager
     */
    private $manager;
    
    /**
     * @var Elasticsearch
     */
    private $client;    
    
    /**
     * ElasticSearch index where Recomendation Items are stored.
     * 
     * @var string
     */
    private $index;
    
    /**
     * Array of active recomendation items
     * 
     * @var array
     */
    public  $items;
    
    
    /**
     * Cache user relational data 
     * 
     * @var array
     */
    private $userRelationData;
    
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::getDetails()
     */
    public function getDetails()
    {
        return [
                'name' => 'ElasticSearch engine',
                'description' => 'Provide recommendations using ElasticSearch as backend.'
        ];
    }    
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::getKey()
     */
    public function getKey(){
        return 'elascticsearch';
    }
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::settingsFields()
     */
    public function settingsFields()
    {
        return [
            'host' => [
                'label' => 'ElasticSearch Host',
                'span'  => 'auto',
                'default' => 'http://localhost',
                'required' => true
            ],
            'port' => [
                'label' => 'ElasticSearch Port',
                'span'  => 'auto',
                'default' => '9200',
                'required' => true
            ],  
            'index' => [
                'label' => 'Recomendation engine index',
                'span'  => 'auto',
                'default' => 'friends',
                'required' => true
            ],                          
        ];
    }
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::boot()
     */
    public function boot()
    {
        $this->index = $this->getSettingValue('index', 'friends');
        // Setup mapping if don't exists
        $this->setupIndex();
    }

    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::update()
     */
    public function update($model)
    {
        if($client = $this->getClient()){
            // Get Recomendation Item using classname of the model
            $it   = $this->getItemByModelClass($model);
            // Get the data the engine is using of the instance
            $data = $it->getItemData($model);
            
            // TODO : Find a way to do an atomic update instead of sending all data
            
            $params['index']          = $this->index;
            $params['type']           = strtolower($it->getKey());
            $params['id']             = $model->getKey();
            $params['body']['doc']    = $data;
            
            try{
                $retUpdate = $client->update($params);
            }catch(Missing404Exception $e){
                $params['body'] = $params['body']['doc'];
                $ret = $client->index($params);
            }
            
            return $data;
        }
        return [];
    }
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::populate()
     */
    public function populate(array $itemKeys = null)
    {
        // Long run queries fill memory pretty quickly due to a default
        // behavior of Laravel where all queries are log in memory. Disabling
        // this log fix the issue. See http://laravel.com/docs/4.2/database#query-logging
        
        DB::connection()->disableQueryLog();
         
        $client = $this->getClient();
    
        $itemKeys = (is_null($itemKeys)) ? array_keys($this->items) : array_map('strtolower', $itemKeys);        
        
        foreach($this->items as $it){
            $key        = strtolower($it->getKey());
            
            if(!in_array($key, $itemKeys)){
                continue; // Skip item
            }
              
            $query      = $it->getQuery();
            $total      = $query->count();
            $current    = 0;
            $batch      = 50;
            $start      = 0;
            
            // Data to be inserted or updated in ElasticSearch
            $bulk       = ['body'=>[]];
            
            while($current < $total){          
                Log::info(sprintf('Processing batch %s [%s, %s] of %s', get_class($it), $start, $batch, $total));
                log::debug( 'Memory usage ' . round(memory_get_usage() / 1048576, 2) . 'Mb');
                
                $collection = $query->skip($start)->take($batch)->get();
                foreach($collection as $row){
                    $data = $it->getItemData($row);
                    
                    // Further information at http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_indexing_operations.html
                    // Action
                    $bulk['body'][] = [
                        'index' => [ 
                            '_id'       => $row->getKey(),
                            '_index'    => $this->index,
                            '_type'     => $key
                        ]
                    ];
                    
                    // Metadata
                    // drop primary key field if exists
                    unset($data[$row->getKeyName()]);
                    $bulk['body'][] = $data;
                    
                    $current ++;
                    // Reset maximum execution timeout
                    set_time_limit(60);
                }
                $start = $start + $batch;
                
                // Bulk insert ElasticSearch
                $client->bulk($bulk);
                
                unset($collection);
                unset($bulk);
                
                Log::info(sprintf('ElasticSearch bulk call [ %s : %s ] added ( %s )', $this->index, $it->getKey(), $batch ));
                log::debug( 'Memory usage ' . round(memory_get_usage() / 1048576, 2) . 'Mb');
            }
    
        }
        
        // Cleaning ElasticSearch cache
        $client->indices()->clearCache(['index'=>$this->index]);
        
        DB::connection()->enableQueryLog();
    }
        
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::clean()
     */
    public function clean(array $itemKeys = null){
        $params = [];
        $params['index'] = $this->index;
        
        if($client = $this->getClient(false)){
            if(is_array($itemKeys)){
                if (count($itemKeys) > 0){
                    $params['type'] = $itemKeys;
                }    
            } 
            if(@$params['type']){
                $ret = $client->indices()->deleteMapping($params);
            }else{
                Log::debug('Cleaning all');
                $ret = $client->indices()->delete($params);
            }
        }
    }
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::suggest()
     */
    public function suggest($user, array $itemKeys, $limit=null)
    {
        // Get combine results of requested items
        $result = [];
        foreach($itemKeys as $key){
            $col = $this->collaborativeFiltering($user, $key, $limit);
            $result[$key] = $col;
        } 
        return $result;
    }

        
    /**
     * Parser ElasticSearch result and return Model instances.
     * Note: this method do not follow pagination only first page of results 
     * is parse.
     * 
     * @param array $ESResult ElasticSearch result
     * @param boolean $eloquentModels 
     * If true return a Collection of Eloquent models  of each matched user.
     * If false return a Collection of Ids of each matched user.     
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function parseResult(array $ESResult, $eloquentModels=true)
    {
        $pkByItemType  = [];
        $data          = @$ESResult['hits']['hits'];
        if (!is_null($data)){
            foreach($data as $r){
                $pkByItemType[$r['_type']][] = $r['_id'];
            }
            
            $items = [];
            foreach($pkByItemType as $key => $pks){
                $it = @$this->items[$key];
                $col = null;
                if (!is_null($it)){
                    if ($eloquentModels) {
                        $imPks = implode(',', $pks);
                        // Get all matching pks 
                        $col = $it->getQueryScope()
                                  ->whereIn($it->getModelKeyName(), $pks)
                                  ->orderByRaw(\DB::raw("FIELD(id, $imPks)"))
                                  ->get();
                        
                        if(!is_null($col)){
                            $items = array_merge($items, $col->all());
                        }
                        
                    } else {
                        $items = array_merge($items, $pks);
                    }
                }
            }
        }else{
            $items = [];
        }
               
        $c = new Collection($items);
        return $c;
    }
    
    /**
     * Alternative method to get Recomendation Items.
     * 
     * @param array $itemKeys
     * @param string $user
     * @param string $limit
     * @param boolean $SortByTopItems
     * @return \Illuminate\Support\Collection
     */
    protected function getAlternativeRecomendations(array $itemKeys, $user=null, $limit=null, $SortByTopItems=false)
    {
        // Get combine items features
        $result = [];
        foreach($itemKeys as $key){
            $col = $this->queryAlternative($user, $key, $limit, $SortByTopItems);
            $result[$key] = $col;
        }
  
        return new Collection($result);
    }

    /**
     * Build alternative query using the given relation data 
     * 
     * @param array $relData
     * @param string $itemKey
     * @param integer $limit
     * @param boolean $SortByTopItems
     * @return \Illuminate\Support\Collection
     */
    protected function queryAlternative($user, $itemKey, $limit=null, $SortByTopItems=false)
    {
        
        $it =  array_get($this->items, $itemKey, null);
        
        if (is_null($it)) {
            return new Collection([]);
        }
        
        $limitSetting = $itemKey . '_max_recomendations';
        $limit = (is_null($limit)) ? Settings::get($limitSetting, 5): $limit;
        
        // Get related User item feature with the given Item Recommendation
        $relField        = $this->getItemRelationFeatureTo('user', $itemKey);
        $reverseRelField = $this->getItemRelationFeatureTo($itemKey, 'user');
        
        $result  = [];
        
        $params = [];
        $params['index'] = $this->index;
        $params['type']  = $itemKey;
        
        $params['body']['_source'] = false;
        
        $params['body']['from'] = 0;
        $params['body']['size'] = $limit;
         
         
        // Use ElasticSearch terms lookup mechanism
        $filters = [];
        $filters['and'] = [                 
                // Exclude user current Items
                ['not' => [ 'terms' => [
                    '_id' => [
                        'index' => $this->index,
                        'type'  => 'user',
                        'id'    => $user->getKey(),
                        'path'  => $reverseRelField,
                        'cache' => false
                    ],
                    'execution' => 'bool',
                    '_cache'    => false
                    ]
                ]],
        ];
         
        // Filters
        $itemfilters = $this->getItemFilters($it);
        foreach($itemfilters as $filter) {
            $filters['and'][] = $filter;
        }
        
        // Add filters to query
        $params['body']['query']['filtered']['filter'] = $filters;
        
        // Sort by top users
        $sort    = [];
        
        if($SortByTopItems) {
            $sort['_script'] = [
                    'script' => "doc['$relField'].values.size()",
                    'type'   => 'number',
                    'order'  => 'desc'
            ];
        }
        
        // Add weight feature to ElasticSearch sort parameter
        // in order to boost by feature weight
        $weight = $it->getActiveWeightFeature();
        if(!is_null($weight)){
            $sort[$weight] = 'desc';
        }
            
        $params['body']['sort'] = $sort;
        
        $result = $this->search($params);
        return $this->parseResult($result);
        
 
    }    
    
    /**
     * Seudo collaborative filtering using current user items
     * and top users behaviour with similar behaviour to the given 
     * user.
     * 
     * @param \RainLab\User\Models\User $user
     * @param string $itemKey
     */
    public function collaborativeFiltering($user, $itemKey, $limit=null) {
        

        $it = array_get($this->items, $itemKey, null);
        
        if (is_null($it)) {
            return new Collection([]);
        }
        
        // Get active features for make recommendations 
        $features = $it->getActiveFeatures();
        
        // Phase 2 : Get items of similar users and excluded the ones
        // The given user already have and include

        $limitSetting = $itemKey . '_max_recomendations';
        $limit = (is_null($limit)) ? Settings::get($limitSetting, 5): $limit;
        
        // Get related User item feature with the given Item Recommendation 
        $relField        = $this->getItemRelationFeatureTo('user', $itemKey);
        $reverseRelField = $this->getItemRelationFeatureTo($itemKey, 'user');

        $result  = [];

        // Find recommendations base on similar users
        $params = [];
        $params['index'] = $this->index;
        $params['type']  = $itemKey;
        
        $params['body']['_source'] = false;
        
        $params['body']['from'] = 0;
        $params['body']['size'] = strval($limit);

        // Build query base in active features
        $recommendationQuery = [];
        
        // Phase 1 : Get similar users base on the given Item Recomendation and user
        // If user is an active feature for the given recomendation
        if(in_array($relField, $features)) {
            // Get similar users 
            $similarUsers = $this->getSimilarUsersTo($user, $itemKey);
        
            // Filter by similar users
            $recommendationQuery[] = [ 'terms' => [
                $relField => $similarUsers->toArray(),
                'execution' => 'bool',
                '_cache'    => true
            ]];
            
        }

        
        // Drop user feature if exists
        if (($key = array_search($relField, $features)) !== false) {
            unset($features[$key]);
            // reset array index
            $features = array_values($features);
        }

        if (count($features) > 0) {
            $relData =  $this->getUserRelatedItemFeatureData($user);
            $relData = array_get($relData, $itemKey, []);
            
            if( count($relData) > 0 ) {
                // Filter by item feature similarity
                $recommendationQuery[] = [ 'fquery' => [ 'query' => [
                    'more_like_this' => [
                            'fields' => $features,
                            'docs' => $relData,
                            'min_term_freq'     => 1,
                            'max_query_terms'   => 12,
                            'min_doc_freq'      => 1
                ]]]];
            }
            
        }
        
        // Sticky items
        $stickyRules = $it->getStickyItemRules();
        if (count($stickyRules) > 0) {
            $pieces = [];
            foreach( $stickyRules as $k => $v){
                $pieces[] = $k . ':' .$v; 
            }

            $strQuery = implode(' AND ', $pieces);
            $stickyQuery['fquery']['query']['query_string']['query'] = $strQuery;
            $recommendationQuery[] = $stickyQuery;
            
            
            // Check if is necessary a match_all query
            if(count($recommendationQuery) == 1){
                $matchAllQuery['fquery']['query']['match_all'] = new \stdClass();
                $recommendationQuery[] = $matchAllQuery;
            }
        }
        
        $filters = ['and' => ['filters' => [], '_cache' => false ]];
        
        // Here is where the magic is done
        switch (count($recommendationQuery)){
            case 1:
                $filters['and']['filters'] = $recommendationQuery;
                break;
            
            case 2:
                $filters['and'][ 'filters'][] = [ 'or' => [ 'filters' => $recommendationQuery, '_cache' => false ]];
                break; 
        }
                 
        // Exclude user current Items
        $filters['and']['filters'][] = ['not' => [ 'terms' => [
                '_id' => [ 
                    'index' => $this->index,
                    'type'  => 'user',
                    'id'    => $user->getKey(),
                    'path'  => $reverseRelField,
                    "cache" => false
                ],
                'execution' => 'bool',
                '_cache'    => false
                ],
                // Not cache users current Items
                '_cache' => false
        ]];
        
       
        // Filters
        $itemfilters = $this->getItemFilters($it);
        foreach($itemfilters as $filter) {
            $filters['and']['filters'][] = $filter; 
        }
        
        // Add filters to query
        $params['body']['query']['filtered']['filter'] = $filters;
        
        // Sort by top users
        $sort    = [];
        
        // Add weight feature to ElasticSearch sort parameter
        // in order to boost by feature weight
        $weight = $it->getActiveWeightFeature();
        if(!is_null($weight)){
            $sort[$weight] = 'desc';
        }

        $sort['_script'] = [
                'script' => "doc['$relField'].values.size()",
                'type'   => 'number',
                'order'  => 'desc'
        ];
        
        
        $params['body']['sort'] = $sort;
        
        $result = $this->search($params);
        return $this->parseResult($result, true);
    }
    
    /**
     * Query ElasticSearch to get top 10 similar users to a given user
     * base on the data of the given Item Recommendation
     * 
     * @param \RainLab\User\Models\User $user
     * @param string $itemKey
     * 
     * @return array
     * Return array of ids of the matched users
     */
    private function getSimilarUsersTo($user, $itemKey) 
    {

        // Get related feature with User Item Recommendation     
        $relField = $this->getItemRelationFeatureTo($itemKey, 'user');
        
        $result  = [];
         
        // Search similar users to given user
        $params = [];
        $params['index'] = $this->index;
        $params['type']  = 'user';
        
        $params['body']['_source'] = false;
        
        $params['body']['from'] = 0;
        $params['body']['size'] = 20; // Get only top tem users
         
         
        // Use ElasticSearch terms lookup mechanism
        $filters = [];
        $filters['terms'] = [
                $relField => [
                        'index' => $this->index,
                        'type'  => 'user',
                        'id'    => $user->getKey(),
                        'path'  => $relField,
                        "cache" => false
                ],
                'execution' => 'bool',
                '_cache'    => false
        ];
        
        // Add filters to query
        $params['body']['query']['filtered']['filter'] = $filters;
        
        // Sort by top users
        $sort    = [];
        $sort['_script'] = [
                'script' => "doc['$relField'].values.size()",
                'type'   => 'number',
                'order'  => 'desc'
        ];
        
        
        $params['body']['sort'] = $sort;
        
        $result = $this->search($params); 
        return $this->parseResult($result, false);       
    }
    


    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::getTopItems()
     */
    public function getTopItems(array $itemKeys, $user=null, $limit=null){
        return $this->getAlternativeRecomendations($itemKeys, $user, $limit, true);
    }
    
    
    /**
     * {@inheritDoc}
     * @see \DMA\Recommendations\Classes\Backends\BackendBase::getItemsByWeight()
     */
    public function getItemsByWeight(array $itemKeys, $user=null, $limit=null){
         return $this->getAlternativeRecomendations($itemKeys, $user, $limit, false);
    }
    
    
    /**
     * Get an instance of ElasticSeach client
     * 
     * @param boolean $silence 
     * Don't throw exceptions if connection is not successful. Default is true
     * 
     * @return \Elasticsearch\Client  
     * Return a \Elasticsearch\Client if connection settings are correct
     * if setting are not correct null will be returned
     */
    protected function getClient($silence=true)
    {
    	if(is_null($this->client)){
    	    try{
        	    $host = $this->getSettingValue('host');
        	    $port = $this->getSettingValue('port');
        	    if(!is_null($host) && !is_null($port)){
            	    $url  = sprintf('%s:%s', $host, $port);
            	    
                    $params = [];
                	$params['hosts'] = [
                	   $url
                	];
                
                	$this->client = new Elasticsearch\Client($params);
                	$this->client->ping();
                	
        	    }
    	    }catch(\Exception $e){
    	        if($silence){
    	           $this->client = null;
    	           \Log::critical('Can connect ElasticSearch host with this details', $params);
    	        }else{
    	            throw $e;
    	        }
    	    }
    	}
    	return $this->client;
    }   
    
    /**
     * Execute a query serach in ElasticSearch
     *
     * @param boolean $silence
     * Don't throw exceptions if connection or query are not successful. Default is true
     *
     * @return array
     */
    protected function search($params, $silence=true)
    {
       $result = [];
        try{
       
            if($client = $this->getClient($silence)) {
                \Log::debug(json_encode($params));
                $result = $client->search($params);
            }
            
        }catch(\Exception $e){
            if($silence){
                \Log::critical('ElasticSearch :' . $e->getMessage(), $params);
            }else{
                throw $e;
            }
        }
        return $result;
    }
    


    /**
     * Create Recommendation index if does not exists.
     * Return true if the index is created or exists
     *
     * @param string $index 
     *
     * @return bool
     */
    protected function createIndex($index)
    {
    	$params = [];
    	$params['index'] = $index;
    
    	try{
    	    if($client = $this->getClient()){
    		  $ret = $client->indices()->create($params);
    		  return $ret['acknowledged'];
    	    }else{
    	        return false;
    	    }
    	}catch(BadRequest400Exception $e){
            // Index already exists
    	    return true;
    	}
    	return false;
    }
    
    /**
     * Create or Update ElasticSearch index mapping
     */
    protected function setupIndex()
    {
        if($this->createIndex($this->index)){
    	    if($client = $this->getClient()) {
        		foreach($this->items as $it){
        		    $type = strtolower($it->getKey());
        			$params = [];
        			$params['index'] = $this->index;
        			$params['type']  = $type;
        			
        			$mapping = $this->getItemMapping($it);
        			    			
        			// Update the index mapping if necessary
        			$current = $client->indices()->getMapping($params);
        			$updateMapping = true;
    
        			if($current = @$current[$this->index]['mappings'][$type]){
        			    $updateMapping = $current['properties'] != $mapping['properties'];
        			}
    
        			try{
            			if ($updateMapping){
             			     $params['body'][$type] = $mapping;  			
            			     $client->indices()->putMapping($params);
            			}
        			} catch(BadRequest400Exception $e) {
        			    $message = "ElasticSearch type '$type' fails to update its mapping. Run the following commands to address this issue 'curl -XDELETE http://localhost:9200/friends/$type'  './artisan recommendation:populate-engine -i $type'";
        			    \Log::critical($message);
        			}
        		}
    	    }
    	}

           
    }

    /**
     * Get ElasticSearch mapping of the given 
     * Recommendation Item
     * 
     * @param DMA\Recommentations\Classes\Items\ItemBase $it
     * @return array
     */
    protected function getItemMapping($item)
    {
        $properties     = [];
        
        foreach($item->getItemDataFields() as $opts){
            // Get name
            $field = array_shift($opts);
            
            $mapping = array_merge([
                'type' => 'string',
                'analyzer' => 'standard'        
            ], $opts);
            
            // Drop analyzer if type is not string
            if( (strtolower($mapping['type']) != 'string') || 
                (!is_null(@$mapping['index'])) ){
                 unset($mapping['analyzer']);
            }
            
            $properties[$field] = $mapping;
        }
         
        $itemMapping = [
            '_source' => [ 'enabled' => true ],
            'properties' => $properties
        ];
        

        
        // Special case get dynamic templates if getItemMapping exist in Item
        if(method_exists($item, 'getItemMapping')){
            if($extra = $item->getItemMapping($itemMapping)){
                $itemMapping = array_merge($itemMapping, $extra);
            }
        }
        
        return $itemMapping;
    }
    

    /**
     * Get ElasticSearch filter structure for each filter on the 
     * Recommendation itme
     * 
     * @param DMA\Recommentations\Classes\Items\ItemBase $it
     * @return array
     */
    protected function getItemFilters($it)
    {
        $ret = [];
        // Filters
        $filters = $it->getFiltersExpressions($this);
      
        foreach($filters as $filter => $exp ){
            // Is a filter expressed in ElasticSearch DSL
            if(is_array($exp)){
                $ret[] = $exp;
            }if(is_string($exp)){
               $strFilter['fquery']['query']['query_string']['query'] = $exp;
               $ret[] = $strFilter;
            }
        }
        
        return $ret;
    }

    /**
     * Get user data of the relationships with other
     * Recomendation Items.
     *
     * @param \RainLab\User\Models\User $user
     * @return array
     */
    public function getUserRelatedItemFeatureData($user)
    {
        if(is_null($user)) return [];
    
        if(is_null($relData = $this->userRelationData)) {
            // No cached data yet.
    
            $relData = [];
    
            $it    = $this->items['user'];
            // Related users
            $relationFeatures  = $it->getItemRelations();
    
            // Query
            $params['index'] = $this->index;
            $params['type']  = $it->getKey();
            $params['body']['query']['match']['_id'] = $user->getKey();
    
            if($client = $this->getClient()){
                $results = $this->search($params);
                $data = @$results['hits']['hits'];
    
                foreach($data as $row){
                    foreach($relationFeatures as $feature => $class){
                        $relIt = $this->getItemByClass($class);
                        $key = $relIt->getKey();
    
                        $rel = @$row['_source'][$feature];
                         
                        if (!is_null($rel)){
                            if(!is_array($rel)){
                                $rel = [ $rel ];
                            }
                             
                            foreach($rel as $pk){
                                $relData[$key][] = [
                                        '_type' => $key,
                                        '_id'   => $pk
                                ];
                            }
                        }
                    }
                }
            }
    
            // Fill cache
            $this->userRelationData = $relData;
    
        }
    
        return $relData;
    }
    
}