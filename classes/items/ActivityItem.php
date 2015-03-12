<?php namespace DMA\Recommendations\Classes\Items;

use Log;
use Str;
use DMA\Recommendations\Classes\Items\ItemBase;
use Doctrine\DBAL\Query\QueryBuilder;
use Carbon\Carbon;



/**
 * Activity Item 
 * @author Carlos Arroyo
 *
 */
class ActivityItem extends ItemBase
{
    
   
    /**
     * {@inheritDoc}
     * @return array
     */
    public function getDetails()
    {
        return [
                'name' => 'Activities',
                'description' => 'Recommend activities base on tags and user activity.'
        ];
    }
    
    /**
     * {@inheritDoc}
     * @return string
     */
    public function getKey()
	{
		return 'activity';
	}

	/**
     * {@inheritDoc}
     * @return string
	 */
	public function getModel()
	{
	    return '\DMA\Friends\Models\Activity';
	}
	
	/**
     * {@inheritDoc}
     * @return QueryBuilder
	 */
	public function getQueryScope()
	{
	    return parent::getQueryScope()->isActive();
	                                  
	}
	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::addSettingsFields()
	 */
	public function getSettingsFields()
	{
		return [];
	}
  	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getFeatures()
	 */
	public function getFeatures()
	{
		return [
		    ['users',      'type' => 'string', 'index' => 'not_analyzed'],  
            'categories',
		];
	}	


	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getFilters()
	 */
	public function getFilters()
	{
		return [
		  ['time_restrictions', 'type' => 'object'],
		  ['is_active',  'type' => 'boolean' ]      
		];
	}	
	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::addWeightFeatures()
	 */
	public function getWeightFeatures()
	{
		return [
		  ['priority',  'type' => 'integer']
		];
	}

	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getItemRelations()
	 */
	public function getItemRelations()
	{	    
	    return [
	       'users' => '\DMA\Recommendations\Classes\Items\UserItem',
	    ];
	}

	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getStickyItemRules()
	 */
	public function getStickyItemRules()
	{
	   return [
	      'priority' => 11 # Always visible 
	   ];   
	}
	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getUpdateAtEvents()
	 */
	public function getUpdateEvents()
	{
	    $k = $this->getModel();
	    $k = (substr( $k, 0, 1 ) === "\\") ? substr($k, 1, strlen($k)) : $k;
		return [
		  'dma.friends.activity.completed',
		  'eloquent.created: ' . $k,
		  'eloquent.updated: ' . $k   
        ];
	}	
		
	// SPECIAL METHOD FOR OVERRIDE OR EXTEND ELASTICSEARCH MAPPING
	/**
	 * Special method called by ElasticSearchBackend. 
	 * @return array
	 */
	public function getItemMapping($mapping)
	{
	    /*
	    $mapping['date_detection'] = false;
	    */
	    // Define a dynamic template for activity times
	    $mapping["dynamic_templates"] = [
    	    [
    	        "time_fields" => [
    	           "match" =>  "*time",
    	           "mapping" => [
    	               'type' => 'date', 'format'=>'HH:mm:ss' 
    	           ]
    	       ]
    	    ]
	    ];	    
	    
	    return $mapping;
	}
	
	
	// PREPARE DATA METHODS
	public function getCategories($model)
	{

	    $clean = [];
	    $model->categories->each(function($r) use (&$clean){
	       //$clean[] = [ 'id' => $r->getKey(), 'name' => $r->name ];
	        $clean[] = $r->name;
	    });
	    return $clean;

	}
	
	public function getIsActive($model)
	{
	    return (!$model->is_archived && $model->is_published);
	}
	
	
	public function getTimeRestrictions($model)
	{
	       
	    $restrictions = [];
	    $keys         = ['date_begin', 'date_end', 'start_time', 'end_time', 'days'];
	    $dayNames     = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
       
        $type         = $model->time_restriction;
        $data         = $model->time_restriction_data; // Get deserializated time restrictions data
        
        // Reset values
        foreach($keys as $key){
            $restrictions[$key] = null;
        }
        
        switch($type){
            case 0:
                break;
            
            case 1: // Days / Hours
                $days = [];
                foreach($data['days'] as $key => $value){
                    $days[ $dayNames[$key-1] ] = $value;
                }
                $restrictions['days'] = $days;

                $restrictions['start_time'] = $this->normalizeTime($data['start_time']);
                $restrictions['end_time']   = $this->normalizeTime($data['end_time']);
                
                break;
            
            case 2: // Date range
                $restrictions['date_begin'] = $this->carbonToIso($model->date_begin, 'date');
                $restrictions['date_end']   = $this->carbonToIso($model->date_end, 'date');
                
                $restrictions['start_time'] = $this->carbonToIso($model->date_begin, 'time');
                $restrictions['end_time']   = $this->carbonToIso($model->date_end, 'time');

                
                break;
        }
        // Store type
	    $restrictions['type']       = $type;

	    return $restrictions;
	}
	
    protected function carbonToIso($carbonDate, $bit=null)
    {
        if(!is_null($carbonDate)){
            if (is_null($bit)){
        	   return $carbonDate->toIso8601String();
            }else if ($bit == 'date'){
               return $carbonDate->toDateString();
            }else if ($bit == 'time'){
                return $carbonDate->toTimeString();
            }
        }        
    }
    
    protected function normalizeTime($time)
    {
        if(!is_null($time)){
            return Carbon::parse($time)->toTimeString();
        }
    }
    
    #########
    # Filters
    #########
    
    public function filterTimeRestrictions($backend)
    {
        $today = new Carbon();        
        
        // Get day name
        $day = strtolower($today->format('l'));

        // Split date and time
        $date = $today->toDateString();
        $time = $today->toTimeString();
        
        // Filters using SOLR syntax. ElasticSearch DSL can
        // be used if the return value is an Array
         
        $filter = '';
        
        // Activities without timerestrictions
        $filter .= "( time_restrictions.type:0 )";

        
        // restriction type 1 ( Day and Time )
        $filter .= " OR ";
        $filter .= "( time_restrictions.type:1 AND 
                      time_restrictions.days.$day:true  
                      time_restrictions.start_time:[ * TO $time ] AND 
                      time_restrictions.end_time:[ $time TO * ] )";        
                
        // restriction type 2 ( Date and Time )
        $filter .= " OR ";
        $filter .= "( time_restrictions.type:2 AND 
                      time_restrictions.date_begin:[ * TO now ] AND 
                      time_restrictions.date_end:[ now TO * ] AND 
                      time_restrictions.start_time:[ * TO $time ] AND 
                      time_restrictions.end_time:[ $time TO * ] )";
                
        return $filter;
       
    }
    
    public function filterIsActive($backend)
    {
        // Because there are activities without time restricitons but they are archived or not published
        // is better exclude them as well.
        $filter = 'is_active:true';
        
        // Excluded from recomendations items with priority = 0 ( Hide )
        $filter .= ' AND ';
        $filter .= '-priority:0';
        
        return $filter;
    }
    
    
}
