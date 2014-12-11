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

class ElasticSearchBackend extends BackendBase
{
    
    # Funtions:
    # 1. Create mappings
    # 3. Update mapings
    # 2. Index items
    
    
    private $manager;
    private $client;    
    private $index;
    public  $items;
    
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
  
            $query      = $it->getQueryScope();
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
        
        $relData = $this->getUserRelatedItemFeatureData($user);
        
        // Get combine items features
        $result = [];
        foreach($itemKeys as $key){
            $rel = @$relData[$key];
            //$rel = (is_null($rel)) ? [] : $rel; 
            if(!is_null($rel)){
                $col = $this->query($rel, $key, $limit);
            }else{
                $col=[];
            }
            $result[$key] = $col;
        }
  
        return new Collection($result);
    }
       
    protected function query($relData, $itemKey, $limit=null)
    {
        $sort   = [];
        
        $it = $this->items[$itemKey];
   		$fields = $it->getActiveFeatures();
   		
   		$limitSetting = $itemKey . '_max_recomendations'; 
   		$limit = (is_null($limit)) ? Settings::get($limitSetting, 5): $limit;
        
		// add weight feature to ElasticSearch sort parameter
		$weight = $it->getActiveWeightFeature();
		if(!is_null($weight)){
			$sort[] = $weight;
		}

        $result = [];
        if(count($fields) > 0 ){
        
        	// Create query
        	$params = [];
        	$params['index'] = $this->index;
        	$params['type']  = $itemKey;
        
        	$params['body']['_source'] = false;
        
        	$params['body']['from'] = 0;
        	$params['body']['size'] = $limit;
        
        	$params['body']['fields'] = [ '_id' ];
        	
            // Query
        	$query = [
            	'more_like_this' =>
            	[
                	'fields' => [
                	   $fields
                	],
                	
                	'docs' => $relData,
            	    
            	    'min_term_freq'     => 1,
            	    'max_query_terms'   => 12,
            	    'min_doc_freq'      => 1
            	]
        	];
        
        	$params['body']['query']['filtered']['query'] = $query;
        	
        	// Boost by feature weight
        	$sort = array_map(function($r){
        		return [$r => 'desc'];
        	},array_merge($sort, ['_score']));
        
        	$params['body']['sort'] = $sort;
        	//return $params;
        
        	$result = $this->client->search($params);
        }
        return $this->parseResult($result);        
    }
    
    public function getUserRelatedItemFeatureData($user)
    {
        $it    = $this->items['user'];
        // Related users
        $relationFeatures   = $it->getItemRelations();

        $relData = [];
        
        if(!is_null($user)){
            // Query
            $params['index'] = $this->index;
            $params['type']  = $it->getKey();
            $params['body']['query']['match']['_id'] = $user->getKey();
            
            if($client = $this->getClient()){
                $results = $client->search($params);
                $data = @$results['hits']['hits'];
          
                foreach($data as $row){
                    foreach($relationFeatures as $key => $feature){
        
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
        }
        
        return $relData;
    }
    
    /**
     * Parser ElasticSearch result and return Model instances
     * @param array $ESResult ElasticSearch result
     * @return Illuminate\Support\Collection
     */
    protected function parseResult(array $ESResult)
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
                    $imPks = implode(',', $pks);
                    // Get all matching pks in this item preserving the elastic serarch
                    // per item
                    $col = $it->getQueryScope()
                              ->whereIn($it->getModelKeyName(), $pks)
                              ->orderByRaw(\DB::raw("FIELD(id, $imPks)"))
                              ->get();
                }
                if(!is_null($col)){
                    $items = array_merge($items, $col->all());
                }
            }
        }else{
            $items = [];
        }
               
        $c = new Collection($items);

        return $c;
    }
    

    /**
     * Get an instance of ElasticSeach client
     * 
     * @param boolean $silence 
     * Don't throw exceptions if connection is not successful. Default is true
     * 
     * @return mixed 
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
     * Create recomendation index if does not exists.
     * Return true if the index is created or exists
     *
     * @param $index 
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
    
    
    protected function setupIndex()
    {
    
    	if($this->createIndex($this->index)){
    	    $client = $this->getClient();
    		foreach($this->items as $it){
    		    $type = strtolower($it->getKey());
    			$params = [];
    			$params['index'] = $this->index;
    			$params['type']  = $type;
    
    			$mapping = $this->getItemMapping($it);
    			    			
    			// Update the index mapping if necessary
    			$current = $client->indices()->getMapping($params);
    			$updateMapping = true;
    			//Log::info($current);
    			if($current = @$current[$this->index]['mappings'][$type]){
    			    $updateMapping = $current['properties'] != $mapping['properties'];
    			}
    			
    			if ($updateMapping){
    			     Log::info('mapping updated ', $mapping);
    			     $params['body'][$type] = $mapping;  			
    			     $client->indices()->putMapping($params);
    			}
    		}
    
    		//Log::info($ret);
    	}
           
    }
    
    protected function getItemMapping($item)
    {
        $properties     = [];
        
        // TODO : allow to specify ElasticSearch type of field
        foreach($item->getItemDataFields() as $opts){
            // Get name
            $field = array_shift($opts);
            
            $mapping = array_merge([
                'type' => 'string',
                'analyzer' => 'standard'        
            ], $opts);
            
            // Drop analyzer if type is not string
            if(strtolower($mapping['type']) != 'string'){
                unset($mapping['analyzer']);
            }
            
            $properties[$field] = $mapping;
        }
         
        return [
            '_source' => [ 'enabled' => true ],
            'properties' => $properties
        ];
    }
    
}

