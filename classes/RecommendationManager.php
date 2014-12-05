<?php namespace DMA\Recommendations\Classes;

use DMA\Recommendations\Models\Settings;

/**
 * DMA recomendation manager 
 * @author Carlos Arroyo
 */
class RecommendationManager
{
    
    /**
     * Dictionary of Recommendation Items
     * @var array
     */
    private $items = [];

    /**
     * Dictionary of Recommendation Backends
     * @var array
     */
    private $backends = [];
    
    /**
     * Flag to keep track if Item Events are 
     * already bind to the engine
     * @var boolean
     */
    private $bindEvents = false;
    
    /**
     * Active recomendation backend engine
     * @var DMA\Recommendations\Classes\Backends\BackendBase
     */
    private $engine = null;
    
    /**
     * Register recomentation items
     * @param array $items classnames of Recommendation Items to be register
     */
    public function registerItems(array $items)
    {
    	foreach($items as $class => $details){
    
    		$it = \App::make($class);
    
    		// Fill detail information used by the Admin interface
    	    $it->info = $details;
            // Is this item active
    	    $it->active = Settings::get(strtolower($it->getKey()) .'_active', false);  	    
    	    
    	    $this->items[$it->getKey()] = $it;
    	
    	}
    	
    }

    
    /**
     * Return an array of all register Recommendation Items
     * @return array
     */
    public function getRegisterItems()
    {
        return $this->items;
    }
   
    /**
     * Register recomentation backend
     * @param array $backends classnames of recomedation backends
     */
    public function registerBackends(array $backends)
    {
    	foreach($backends as $class => $details){
    
    		$backend = \App::make($class);
    
    		// Fill detail information used by the Admin interface
    		
    		$backend->info = $details;
    		$this->backends[$backend->getKey()] = $backend;
    		
    		// TODO : read settings to set active engine
    		$this->engine = $backend;
    	}
        
        // Initialize active Recomendation Backend engine
        // Only pass active items to Recomendations Backend
    	$this->engine->items = array_filter($this->items, function($it){
    	    return $it->active;
    	});

    	$this->engine->boot($this);
    	
    	// Temporal
    	$this->populateEngine();
    	
    }
    
    
    /**
     * Return an array of all register Recommendation Backends
     * @return array
     */
    public function getRegisterBackends()
    {
    	return $this->backends;
    }
    
    /**
     * Return and array of recomendations of the given user.
     *
     * @param RainLab\User\Models\User $user
     *
     * @param array $itemKeys
     * An array of Items requested to be returned by the recomendation engine.
     *
     * @param int $limit
     * If limit is not given configured values per Item in the admin interface
     * will be use.
     *
     * @return array
     * Mixed array of October\Rain\Database\Model
     */    
    public function suggest($user, $itemKeys=null, $limit=null)
    {
        if(!is_null($this->engine)){

            if (is_array($itemKeys)){
                $itemKeys = (count($itemKeys) == 0) ? null : $itemKeys; 
            }else{
                $itemKeys = [strtolower($itemKeys)];
            }
            
            
            // Get all item keys is null or empty array
            $itemKeys = (is_null($itemKeys)) ? array_keys($this->items) : $itemKeys;

            // Make sure they are lowercase and remove inactive items
            $keys = [];
            foreach($itemKeys as $key){
                $key = strtolower($key);
                if(@$this->items[$key]->active){
                    $keys[] = $key;
                }
            }
           
            return $this->engine->suggest($user, $keys, $limit);
        }
        return [];
    }
    
    /**
     * Suscribe all events of the active Items into the active Recomendation backend engine
     */
    public function bindEvents()
    {

        if (!$this->bindEvents){
            // Bind update events to active engine
            $this->engine->bindUpdateEvents();
        }
        return $this->bindEvents;
    }
    
    
    /**
     * Populate all data of each item into the active Recomendation backend engine 
     */
    public function populateEngine()
    {
        $this->engine->populate();
    }
}