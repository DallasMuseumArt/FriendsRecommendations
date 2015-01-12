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
    	foreach($items as $class){

    	    // Create a Item instance
    		$it = \App::make($class);

            // Is this item active
    	    $it->active = Settings::get(strtolower($it->getKey()) .'_active', true);  
    	    // Non editable items should be active always
    	    $it->active = (!$it->adminEditable) ? true : $it->active;
    	    
    	    $this->items[strtolower($it->getKey())] = $it;
    	
    	}
    }

    
    /**
     * Return an array of all register Recommendation Items
     * @param boolean $excludeHidden Exclude hidden items. Default true
     * @return array
     */
    public function getRegisterItems($excludeHidden=true)
    {
        if ($excludeHidden){
            return array_filter($this->items, function($it){
                return $it->adminEditable;
            });
        }else{
            return $this->items;
        }
        
    }
   
    
    
    /**
     * Register recomentation backend
     * @param array $backends classnames of recomedation backends
     */
    public function registerBackends(array $backends)
    {
    	foreach($backends as $class){
    
    		// Create a Backend instance
    	    $backend = \App::make($class);
    
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
     * Return the active Recommendation backend engine
     * @return DMA\Recommendations\Classes\Backends\BackendBase
     */
    public function getActiveBackend()
    {
    	return $this->engine;
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
            
            $keys = $this->getItemKeys($itemKeys);           
            return $this->engine->suggest($user, $keys, $limit);
        }
        return [];
    }
    
    /**
     * Return and array of top or most popular recommentaion items.
     * 
     * @param RainLab\User\Models\User $user
     * If User is given recomendation items from the user will be excluded.
     *  
     * @param array $itemKeys 
     * An array of Items requested to be returned by the recomendation engine.
     * 
     * @param int $limit 
     * If limit is not given configured values per Item in the admin interface
     * will be use.
     * 
     * @return Illuminate\Support\Collection
     * Mixed Collection of October\Rain\Database\Model
     */
    public function getTopItems($user=null, array $itemKeys=null, $limit=null)
    {
        if(!is_null($this->engine)){
    
            $keys = $this->getItemKeys($itemKeys);
            return $this->engine->getTopItems($keys, $user, $limit);
        }
        return [];
    }

    /**
     * Return and array of recommentaion items sorted by weight field if is defined
     * in the recommendation item. If not weight field configured or active Items will be
     * returned in the default natural order of the Backend implemented.
     *
     * @param RainLab\User\Models\User $user
     * If User is given recomendation items from the user will be excluded.
     *
     * @param array $itemKeys 
     * An array of Items requested to be returned by the recomendation engine.
     *  
     * @param int $limit 
     * If limit is not given configured values per Item in the admin interface
     * will be use.
     *
     * @return Illuminate\Support\Collection
     * Mixed Collection of October\Rain\Database\Model
     */ 
    public function getItemsByWeight($user=null, array $itemKeys=null, $limit=null)
    {
        if(!is_null($this->engine)){
    
            $keys = $this->getItemKeys($itemKeys);  
            return $this->engine->getItemsByWeight($keys, $user, $limit);
        }
        return [];
    }
    
    /**
     * Internal method for cleaning and getting Active ItemKeys
     * 
     * @param array $itemKeys 
     * An array of Items requested to be returned by the recomendation engine.
     * 
     * @return array
     */
    private function getItemKeys(array $itemKeys=null)
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
                        
            return $itemKeys;
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
     * 
     * @param array $itemKeys list of itmeKeys to populate.
     * 
     * The item has to be active in order to ingest data into the recomendation engine.
     * If not given an array all active itmes will be ingested.
     */
    public function populateEngine(array $itemKeys = null)
    {
        $this->engine->populate($itemKeys);
    }
    
    
    /**
     * Clean all data into the backend
     *
     * @param array $itemKeys list of itmeKeys to be deleted.
     *
     * If not given an array all active itmes will be ingested.
     */
     public function cleanEngine(array $itemKeys = null)
     {
        $this->engine->clean($itemKeys);
     }
     
     

     /**
      * Update data of the given Item ID in the backend.
      *
      * @param string Item key where the objects bellows to
      * @param mixed|Integer $id
      */
     public function updateItem($itemKey, $id)
     {
         $this->engine->updateItemById($itemKey, $id);
     }
}