<?php namespace DMA\Recomendations\Classes\Items;

use Log;
use Dma\Recomendations\Classes\Items\ItemBase;



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
	        //'users',	        
            'categories',
	    ];
	}

	/**
	 * {@inheritDoc}
	 * @see \DMA\Recomendations\Classes\Items\ItemBase::addFilters()
	 */
	public function getFilters()
	{
		return [];
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
		  'friends.badgeEarned'
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
