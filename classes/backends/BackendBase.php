<?php namespace DMA\Recommendations\Classes\Backends;

use Log;
use Event;
use DMA\Recomendations\Classes\RecomendationManager;
use DMA\Recomendations\Classes\Exceptions\ItemNotFoundException;

/**
 * @author Carlos Arroyo
 *
 */
abstract class BackendBase
{   
    /**
     * An associative array to all active Items.
     * The key of this array is the Item Key.
     * 
     * e.g. [ 'activity' => $activityItemInstance ]
     * 
     * @var array
     */
    public $items;
    
    /**
     * An array to all active Items
     * The key of this array is the Item class of the model.
     * 
     * e.g. [ 'DMA\Friends\Models\Activity' => $activityItemInstance ]
     * 
     * @var array
     */    
    protected $itemsByClass;
    
    
    /**
     * Unique ID identifier of the Backend.
     *
     * @return string
     */
    abstract public function getKey();    
    
    /**
     * Configure specific settings fields for the backend.
     * For futher information go to http://octobercms.com/docs/plugin/settings#database-settings
     *
     * @return array
     */
    abstract public function settingsFields();    
    
    /**
     * Always called by the recomendation engine.
     * This is a good place to init connections and configure 
     * schemas if required.
     * 
     */
    abstract public function boot();
    
    /**
     * Update data of the given Item in the backend.
     * 
     * @param October\Rain\Database\Model $model
     */
    abstract public function update($model);
    
    /**
     * Load all data into the backend
     */
    abstract public function populate();

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
     * @return Illuminate\Support\Collection
     * Mixed Collection of October\Rain\Database\Model
     */
    abstract public function suggest($user, array $itemKeys, $limit=null);
    
    /**
     * Return Recomendation Item instance by model class
     * 
     * @param October\Rain\Database\Model $model
     * 
     * @return DMA\Recomendations\Classes\Items\ItemBase 
     */
    protected function getItemByModelClass($model){
        if(is_null($this->itemsByClass)){
            $this->itemsByClass =[];
            foreach($this->items as $key => $it ){
                $k = $it->getModel();
                $k = (substr( $k, 0, 1 ) === "\\") ? substr($k, 1, strlen($k)) : $k;
                $this->itemsByClass[$k] = $it;
            }
        }

        try{
            $class = get_class($model);
            return $this->itemsByClass[$class];
        }catch(\ErrorException $e){
            throw new ItemNotFoundException('Item with model class ' . $class . ' not found or Item is not active' );
        }
        
    }
    
    
    /**
     * Bind all item models to update data on the recomendation engine.
     */
    public function bindUpdateEvents()
    {
        foreach($this->items as $it){
            $events = $it->getUpdateEvents();
            foreach($events as $evt => $fn){
                
                if (is_callable($fn)){
                    // Change context of the clouse from the Item to the current Backend engine
                    $fn = $fn->bindTo($this);                    
                } else {
                    // Bind and event with out clouser
                    $evt  = $fn; 
                    $item = $it;
                    // Trying to be smart here.
                    // Create a generic clouser. This clouser will update
                    // in the recomendation engine any maching Recomendation item in the engine.
                    $fn = function() use ($item){
                        foreach(func_get_args() as $arg){
                            if(is_object($arg)){
                                if(is_subclass_of($arg, 'October\Rain\Database\Model')){
                                    try{
                                        // Update object in the engine
                                        $this->update($arg);
                                    }catch(ItemNotFoundException $e){
                                        Log::error('Trying to update in the Recomendation engine a not Active or Register Item for the model' . get_class($arg));
                                    }
                                }
                            }
                        }
                    };
                }

                // Start listening this event
                Event::listen($evt, $fn);
            }

            
        }
    }

}
