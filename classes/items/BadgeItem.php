<?php namespace DMA\Recommendations\Classes\Items;

use Log;
use DMA\Recommendations\Classes\Items\ItemBase;



/**
 * Badge Item 
 * @author Carlos Arroyo
 *
 */
class BadgeItem extends ItemBase
{
    /**
     * {@inheritDoc}
     * @return string
     */
    public function getKey()
	{
		return 'badge';
	}

	/**
	 * {@inheritDoc}
	 * @return string
	 */
	public function getModel()
	{
	    return '\DMA\Friends\Models\Badge';
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
	        //'users',	        
            'categories',
	    ];
	}

	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::addFilters()
	 */
	public function getFilters()
	{
		return [];
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
		  'dma.friends.badge.completed',
		  'eloquent.created: ' . $k,
		  'eloquent.updated: ' . $k
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
}
