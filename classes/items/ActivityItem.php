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
	    return parent::getQueryScope()->where('is_published', true);
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
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::addFeatures()
	 */
	public function getFeatures()
	{
		return [
		    'users',
		    'categories'
		];
	}	


	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::addFilters()
	 */
	public function getFilters()
	{
		return [
		  ['time_restrictions', 'type' => 'object' ]
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
		  'user' => 'users',
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
	    
	    // Define a dynamic template for activity times
	    $mapping["dynamic_templates"] = [
    	    [
    	        "time_fields" => [
    	           "match" =>  "start_time",
    	           "match_pattern" => "regex",
    	           "mapping" => [
    	               "type"=> "date",
    	               "format" => 'date_time_no_millis'
    	           ]
    	       ]
    	    ]
	    ];	    
	    */
	    return $mapping;
	}
	
	
	// PREPARE DATA METHODS
	public function getCategories($model){

	    $clean = [];
	    $model->categories->each(function($r) use (&$clean){
	       //$clean[] = [ 'id' => $r->getKey(), 'name' => $r->name ];
	        $clean[] = $r->name;
	    });
	    return $clean;

	}
	
	public function getTime_restrictions($model){
	       
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
}
