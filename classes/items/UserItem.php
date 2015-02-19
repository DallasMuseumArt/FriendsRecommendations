<?php namespace DMA\Recommendations\Classes\Items;

use Log;
use DMA\Recommendations\Classes\Items\ItemBase;
use Doctrine\DBAL\Query\QueryBuilder;



/**
 * User Item 
 * @author Carlos Arroyo
 *
 */
class UserItem extends ItemBase
{
    /**
     * This item can be editable by CMS admin
     * @var boolean
     */
    public $adminEditable = false;
    
    
    /**
     * {@inheritDoc}
     * @return array
     */
    public function getDetails()
    {
        return [
                'name' => 'Users',
                'description' => ''
        ];
    }    
    
    /**
     * {@inheritDoc}
     * @return string
     */
    public function getKey()
	{
		return 'user';
	}

	/**
     * {@inheritDoc}
     * @return string
	 */
	public function getModel()
	{
	    return '\RainLab\User\Models\User';
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
		    ['activities', 'type' => 'string', 'index' => 'not_analyzed'],
		    ['badges',     'type' => 'string', 'index' => 'not_analyzed'],
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
		return [];
	}
	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getItemRelations()
	 */
	public function getItemRelations()
	{
	    return [
            'activities' => '\DMA\Recommendations\Classes\Items\ActivityItem',
            'badges'     => '\DMA\Recommendations\Classes\Items\BadgeItem',
	    ];
	}
	
	/**
	 * {@inheritDoc}
	 * @see \DMA\Recommendations\Classes\Items\ItemBase::getUpdateAtEvents()
	 */
	public function getUpdateEvents()
	{
		return [];
	}	
}
