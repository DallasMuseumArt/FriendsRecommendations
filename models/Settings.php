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
    
    public $settingsCode = 'dma_friends_recommendations_settings';
    public $settingsFields = 'fields.yaml';    
    

    
    /**
     * Default values to set for this model, override
     */
    public function initSettingsData()
    {

    }        
        
    private function setDefaultsFromFields($fields)
    {
        if(is_array($fields)){
        	foreach($fields as $key => $opts){
        		$default = @$opts['default'];
        		if(!is_null($default)){
        			$this->{$key} = $default;
        		}
        	}
        }
    } 
    
 }