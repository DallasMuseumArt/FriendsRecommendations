<?php namespace DMA\Recommendations\Classes\Backends;

use Log;
use Event;
use DMA\Recommendations\Models\Settings;
use DMA\Recomendations\Classes\RecomendationManager;
use DMA\Recommendations\Classes\Exceptions\ItemNotFoundException;
use DMA\Recommendations\Classes\Exceptions\ItemRelationFeatureNotFoundException;

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
     * e.g. [ 'DMA\Recommendations\Classes\Items\ActivityItem' => $activityItemInstance ]
     *
     * @var array
     */
    protected $itemsByClass;
    
    /**
     * An array to all active Items
     * The key of this array is the Item class of the model.
     * 
     * e.g. [ 'DMA\Friends\Models\Activity' => $activityItemInstance ]
     * 
     * @var array
     */    
    protected $itemsByModelClass;
    
    /**
     * Return details of the Item.
     * Manly used in the Backend interface.
     *
     * @return array
     *
     * eg.
     * [
     *  	'name' => 'ElasticSearch',
     *  	'description' => 'Provide recommendations using ElasticSearch as backend'
     * ]
     */
    abstract public function getDetails();    
        
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
     * Get settings value for this backend 
     * 
     * @param string $name
     * @param mixed $default
     */
    protected function getSettingValue($name, $default = null)
    {
        return Settings::get(strtolower($this->getKey()) . '_' . $name , $default);
    }
    
    /**
     * Get recommendation item settings fields including commong field settings
     * All settings are prefixed with the key identifier of the recommendation item.
     *
     * @return array
     */
    public function getPluginSettings()
    {
        $settings = [];
         
        $key = strtolower($this->getKey());
        foreach($this->settingsFields() as $k => $v){
        	$settings[$key . '_' . $k] = $v;
        }
        
        return $settings;
    }
    
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
     * Update data of the given Item ID in the backend.
     *
     * @param string Item key where the objects bellows to
     * @param mixed|Integer $id
     */
    public function updateItemById($itemKey, $id)
    {
    
        if(!is_null($itemKey)){
            $key  = strtolower($itemKey);
            if($it = array_get($this->items, $key, null)){
                $query = $it->getQueryScope();
                $model = $query->find($id);
                $this->update($model);
            }else{
                throw new ItemNotFoundException('Item with key ' . $key . ' not found.' );
            }
        }
    }    
    
    /**
     * Load all data into the backend
     * 
     * @param array $itemKeys list of itmeKeys to populate.
     * 
     * The item has to be active in order to ingest data into the recomendation engine.
     * If not given an array all active itmes will be ingested. 
     */
    abstract public function populate(array $itemKeys = null);

    /**
     * Clean all data into the backend
     *
     * @param array $itemKeys list of itmeKeys to be deleted.
     *
     * If not given an array all active itmes will be ingested.
     */
    abstract public function clean(array $itemKeys = null);
    
    
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
     * Return and array of top or most popular recommentaion items.
     * 
     * 
     * @param array $itemKeys 
     * An array of Items requested to be returned by the recomendation engine.
     * 
     * @param RainLab\User\Models\User $user
     * If User is given recomendation items from the user will be excluded.
     * 
     * @param int $limit 
     * If limit is not given configured values per Item in the admin interface
     * will be use.
     * 
     * @return Illuminate\Support\Collection
     * Mixed Collection of October\Rain\Database\Model
     */
    abstract public function getTopItems(array $itemKeys,  $user=null, $limit=null);

    
    /**
     * Return and array of recommentaion items sorted by weight field if is defined
     * in the recommendation item. If not weight field configured or active Items will be
     * returned in the default natural order of the Backend implemented.
     *
     * @param array $itemKeys 
     * An array of Items requested to be returned by the recomendation engine.
     * 
     * @param RainLab\User\Models\User $user
     * If User is given recomendation items from the user will be excluded.
     * 
     * @param int $limit 
     * If limit is not given configured values per Item in the admin interface
     * will be use.
     *
     * @return Illuminate\Support\Collection
     * Mixed Collection of October\Rain\Database\Model
     */    
    abstract public function getItemsByWeight(array $itemKeys, $user=null, $limit=null);
    
    
    /**
     * Return Recomendation Item instance by model class
     * 
     * @param October\Rain\Database\Model $model
     * 
     * @return DMA\Recomendations\Classes\Items\ItemBase 
     */
    protected function getItemByModelClass($model){
        if(is_null($this->itemsByModelClass)){
            $this->itemsByModelClass =[];
            foreach($this->items as $key => $it ){
                $k = $it->getModel();
                $k = (substr( $k, 0, 1 ) === "\\") ? substr($k, 1, strlen($k)) : $k;
                $this->itemsByModelClass[$k] = $it;
            }
        }

        try{
            $class = get_class($model);
            return $this->itemsByModelClass[$class];
        }catch(\ErrorException $e){
            throw new ItemNotFoundException('Item with model class ' . $class . ' not found or Item is not active' );
        }
        
    }

    /**
     * Return Recomendation Item instance by Item class 
     *
     * @param DMA\Friends\Recommendations\Classes\Items\BaseItem $class
     *
     * @return DMA\Recomendations\Classes\Items\ItemBase
     */
    protected function getItemByClass($class){
        if(is_null($this->itemsByClass)){
            $this->itemsByClass =[];
            foreach($this->items as $key => $it ){
                $k = get_class($it);
                $k = (substr( $k, 0, 1 ) === "\\") ? substr($k, 1, strlen($k)) : $k;
                $this->itemsByClass[$k] = $it;
            }
        }
    
        try{
            $class = (substr( $class, 0, 1 ) === "\\") ? substr($class, 1, strlen($class)) : $class;
            return $this->itemsByClass[$class];
        }catch(\ErrorException $e){
            throw new ItemNotFoundException('Item class ' . $class . ' not found or Item is not active' );
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
                    // Bind and event without clouser
                    $evt  = $fn; 
                    $item = $it;
                    // Trying to be smart here.
                    // Create a generic clouser. This clouser will update
                    // the recomendation engine of any maching Recomendation item in the engine.
                    $fn = function() use ($item, $evt){
                        //Log::debug('called ' .  $evt);
                        
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
    
    
    /**
     * Internally use for finding the name of the feature that
     * is related and a given Item Recommendation
     *
     * @param string $itemKey
     * @param string $relatedToItemKey
     * @return string|NULL
     */
    protected function getItemRelationFeatureTo($itemKey, $relatedToItemKey)
    {
        $feature = null;
        $it      = @$this->items[$itemKey];
        $relItem = @$this->items[$relatedToItemKey];
        if( !is_null($it) && !is_null($relItem) ) {
            $relationFeatures   = $relItem->getItemRelations();
            $itemClass          = get_class($it);
            $itemClass = (substr( $itemClass, 0, 1 ) === "\\") ? $itemClass : "\\" . $itemClass;
    
            // Find relation feature
            if($relFeature = array_search($itemClass, $relationFeatures)){
                return $relFeature;
            } else {
                throw new ItemRelationFeatureNotFoundException("Not relation field found for [$itemKey] to [$relatedToItemKey]");
            }
        } else{
            $message= "Not found Item Recomendations: '$itemKey' or '$relatedToItemKey' ";
            throw new ItemNotFoundException($message);  
        }
        
    }

}
