<?php namespace DMA\Recommendations\Models;

use Model;

/**
 * Friends Recommendations Settings model
 * @package DMA\Recommendations\Models
 * @author Carlos Arroyo
 *
 */
class Settings extends Model{
    
    public $implement = ['System.Behaviors.SettingsModel'];
    
    public $settingsCode = 'friends_recommendations_settings';
    public $settingsFields = 'fields.yaml';    
    

    
    /**
     * Default values to set for this model, override
     */
    public function initSettingsData()
    {

    }        
        
    
 }