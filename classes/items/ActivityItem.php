<?php namespace DMA\Recomendations\Classes\Items;

use Log;
use Dma\Recomendations\Classes\Items\ItemBase;
use Doctrine\DBAL\Query\QueryBuilder;



/**
 * Activity Item 
 * @author Carlos Arroyo
 *
 */
class ActivityItem extends ItemBase
{
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
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::addSettingsFields()
	 */
	public function getSettingsFields()
	{
		return [];
	}
  	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::addFeatures()
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
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::addFilters()
	 */
	public function getFilters()
	{
		return [
		  ['time_restrictions',       'type' => 'object']
        ];
	}	
	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::addWeightFeatures()
	 */
	public function getWeightFeatures()
	{
		return [
		  ['priority',  'type' => 'integer']
		];
	}

	/**
	 * {@inheritDoc}
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::getItemRelations()
	 */
	public function getItemRelations()
	{
		return [
		  'user' => 'users',
		];
	}

	/**
	 * {@inheritDoc}
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::getUpdateAtEvents()
	 */
	public function getUpdateEvents()
	{
		return [
		  'friends.activityCompleted'        
        ];
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
	   
        $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
	    if($restrictions = $model->time_restriction_data){
	       $days = [];
	       foreach($restrictions['days'] as $key => $value){
	           $days[ $dayNames[$key-1] ] = $value;
	       }
	       $restrictions['days'] = $days;	         
	    }
	    
	    $restrictions['type']       = $model->time_restriction;
	    
	    $restrictions['date_begin'] = $this->carbonToIso($model->date_begin);
	    $restrictions['date_end']   = $this->carbonToIso($model->date_end);

	    return $restrictions;
	}
	
    protected function carbonToIso($carbonDate)
    {
        if(!is_null($carbonDate)){
        	return $carbonDate->toIso8601String();
        }        
    }

	public function getDate_begin($model)
	{
	    if($date = $model->date_begin){
	       return $date->toIso8601String();
	    }
	}

	public function getDate_end($model)
	{
		if($date = $model->date_end){
			return $date->toIso8601String();
		}
	}	
}
