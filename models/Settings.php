<?php namespace DMA\Recomendations\Models;

use Model;

/**
 * Friends Recomendations Settings model
 * @package DMA\Recomendations\Models
 * @author Carlos Arroyo
 *
 */
class Settings extends Model{
    
    public $implement = ['System.Behaviors.SettingsModel'];
    
    public $settingsCode = 'friends_recomendations_settings';
    public $settingsFields = 'fields.yaml';    
    

    
    /**
     * Default values to set for this model, override
     */
    public function initSettingsData()
    {

    }        
        
    
 }