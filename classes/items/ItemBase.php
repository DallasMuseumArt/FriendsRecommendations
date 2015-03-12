<?php namespace DMA\Recommendations\Classes\Items;

use Log;
use Doctrine\DBAL\Query\QueryBuilder;
use DMA\Recommendations\Models\Settings;
use Doctrine\DBAL\Types\ArrayType;
use DMA\Recommendations\Facades\Recommendation;

abstract class ItemBase
{
    use \DMA\Recommendations\Traits\StringUtils;
    
    /**
     * This item is active
     * @var boolean
     */
    public $active = true;

    /**
     * This item can be editable by CMS admin
     * @var boolean
     */
    public $adminEditable = true;
    
    /**
     * Array of features and options
     * @var array
     */
    private $features = null;
    
    /**
     * Common Recommedation Item settings
     * @var array
     */
    protected $common_settings = [
        'max_recomendations' => [
            'label' => 'Maximum limit of recommendations',
            'span'  => 'auto',
            'type'  => 'number', 
            //'default' => 5,
            'commentAbove' => 'This value only affects this recommendations item.',
            'required' => true
        ],
        'active' => [
            'label' => 'Is active',
            'span'  => 'auto',
            'type'  => 'checkbox',
            'default' => true ,
            'comment' => 'When disable engine will not get recommendations of this Item.',
        ],
                
        'features' => [
            'label' => 'Features',
            'span'  => 'left',
            'type'  => 'checkboxlist',
            'options' => [],
            'commentAbove' => 'Make recommendations using the following features:',
        ], 

        'filters' => [
            'label' => 'Filters',
            'span'  => 'right',
            'type'  => 'checkboxlist',
            'options' => [],
            'commentAbove' => 'Filter recommendations by one or many of the following filters:',
        ],  

        'weight_by' => [
            'label' => 'Weight',
            'span'  => 'left',
            'type'  => 'dropdown',
            'options' => [],
            'commentAbove' => 'Boost recommendations items by:',
        ]
        
    ];

    /**
     * Return classname of the model that will feed 
     * this recommendation item
     *
     * @return string
     */
    abstract public function getModel();

    /**
     * Return QueryScope to filer the data send to populate 
     * the engine.
     *
     * @return QueryBuilder
     */
    public function getQueryScope()
    {
       return $this->getQuery();
    }
    
    
    public function getQuery()
    {
        $model = $this->getModel();
        $query = new $model;
        return $query;
    }
    
    
    /**
     * Helper method to get the Primary key name field of this model
     * @return string
     */
    public function getModelKeyName()
    {
        // Create an instance of the model to get primary key name
        // I couldn't find a better solution 
        $model = $this->getModel();
        $model = new $model;
        return $model->getKeyName();
    }
    
    
    /**
     * Extract data of the features of the given instance model
     * @param October\Rain\Database\Model $model
     * 
     * @return array
     */
    public function getItemData($model)
    {
       	$data = [];

       	foreach($this->getItemDataFields() as $field){
       	    try {
       	        // Get field name
                $field = $field[0];
                $value = null;
       	        
       	        // Check if a method exists for this feature
       	        $prepareMethod = 'get' . $this->underscoreToCamelCase($field, true);
       	        if (! method_exists($this, $prepareMethod) ){
                    // Using this nasty exception to determinate if 
                    // it is a relation or a simple field. I am doing this 
                    // because other methods I try always trigger a query in 
                    // in the relationship causing a memory leak when 
                    // the relationship is a large dataset 
       	            try {
       	                // log::debug( 'Memory usage ' . round(memory_get_usage() / 1048576, 2) . 'Mb');
                        // Log::info($model->getKey());
                        // Log::info(get_class($model));
                        // Log::info($model->{$field}()->count());
       	                
                        $key = $model->{$field}()->getRelated()->getQualifiedKeyName();
       	                $value = $model->{$field}()->select($key)
       	                                           ->distinct() 
       	                                           ->lists($key);      	               

       	                // log::debug( 'Memory usage ' . round(memory_get_usage() / 1048576, 2) . 'Mb');
       	            }catch (\BadMethodCallException $e){
       	                $value = $model->{$field};
       	            }
     
       	        }else{
       	            // Call prepare method
       	            $value = $this->{$prepareMethod}($model);
       	        }
                
              
       	    } catch(\Exception $e) {
       	        $value = null;
        	    Log::error(sprintf('Extracting Item feature [%s] in [%s]', $field, get_class($this)), [
                    'message' => $e->getMessage(),
                    //'stack'   => $e->getTraceAsString()
       	       ]); 
       	    }
       	    
       	    $data[$field] = $value or '';          	        

       	}
       	return $data;
    }

    
    /**
     * Return details of the Item.
     * Manly used in the Backend interface.
     *
     * @return array
     *
     * eg.
     * [
     *  	'name' => 'User',
     *  	'description' => ''
     * ]
     */
    abstract public function getDetails();

        
    /**
     * Unique ID identifier of the Item.
     *
     * @return string
     */
    abstract public function getKey();
    
    
    /**
     * Configure specific settings fields for this recommendation item.
     * For futher information go to http://octobercms.com/docs/plugin/settings#database-settings
     *
     * @return array
     */
    abstract public function getSettingsFields();
    
    
    /**
     * Get recommendation item settings fields including commong field settings 
     * All settings are prefixed with the key identifier of the recommendation item.
     * 
     * @return array
     */
    public function getPluginSettings()
    {
       # Get common and specific settings
       $combine = array_merge($this->common_settings,  $this->getSettingsFields());
       $settings = [];
       
       $key = strtolower($this->getKey());
       foreach($combine as $k => $v){
            $settings[$key . '_' . $k] = $v;
       }

       # Add Feature list of options 
       $this->addSettingsOptions('features', $settings, $this->getFeatures());

       # Add Feature list of options
       $this->addSettingsOptions('filters', $settings, $this->getFilters());       

       # Add Feature list of options
       $this->addSettingsOptions('weight_by', $settings, $this->getWeightFeatures(), true);
       
       return $settings;
       
    }

    /**
     * Helper function add options list to settings fields. This is used for display  
     * options in OctoberCMS admin interface.
     * 
     * @param string $settingName
     * @param array  $settings
     * @param array  $options
     */
    private function addSettingsOptions($settingName, &$settings, array $options, $emptyValue=false)
    {
        $key = strtolower($this->getKey());
        $idField =  $key. '_' . $settingName;
        
        $fieldSettings = &$settings[$idField];

        if (!is_null($fieldSettings)){
        	
        	if (count($options) > 0){
        		$opts     = &$fieldSettings['options'];   
                $default  = null;
                
       		    if($emptyValue){
        		  $opts[null] = '--empty--';
        		}
        		
        		forEach($options as $k){
           		    $k = (is_array($k)) ? array_shift($k) : $k;
           		    
           		    // If option starts with underscore don't include in settings
           		    if (substr( $k, 0, 1 ) !== "_") {   
           		        $opts[$k] = ucfirst($k);
            			
            			if($emptyValue && is_null($default)){
            			    $default = $k;
            			}
        		    }
        		}
        		
        		if(!is_null($default)){
        		   $fieldSettings['default'] = $default;
        		}
        		
        	}else{
        		unset($settings[$idField]);
        	}
        }       
    } 
    
    /**
     * Get settings value of this Recommendation item
     *
     * @param string $name
     * @param mixed $default
     */
    protected function getSettingValue($name, $default = null)
    {
    	return Settings::get(strtolower($this->getKey()) . '_' . $name , $default);
    }
    
    
    /**
     * Return all declared fields and properties in this Recommendation Item
     * @return array
     */
    public function getItemDataFields(){
        if(is_null($this->features)){
            $features = array_merge(
            		$this->getFeatures(),
            		$this->getFilters(),
            		$this->getWeightFeatures()
            );        
            
            $this->features = array_map(function($f){
                return (!is_array($f)) ? [$f] : $f;
            }, $features); 
        }
        return $this->features;
    }

    /**
     * Return an array of fields of the model that will be used
     * as features of the Recommendation Item. 
     * 
     * @return array
     */
    abstract protected function getFeatures();
    
    /**
     * Return an array of all active features
     * @return array
     */
    public function getActiveFeatures()
    {
        $ret = $this->getSettingValue('features', []);
        return (is_array($ret)) ?  $ret : [];
    }
    

    /**
     * Return an array of filters avaliable by the Recommendation Item.
     * 
     * @return array
     */
    abstract protected function getFilters();

    /**
     * Return an array of all active filters
     * @return array
     */
    public function getActiveFilters()
    {
    	$ret = $this->getSettingValue('filters', []);
    	return (is_array($ret)) ?  $ret : [];
    }

    /**
     * Get filter expressions to be use by the backend 
     * 
     * @param string $backendKey Backend key requesting filters
     * @param array
     */
    public function getFiltersExpressions($backend)
    {
        $filterExpressions = [];
        
        $filters = $this->getActiveFilters();
        foreach($filters as $f){
            // Check if a method exists in this item
            $filterMethod = 'filter' . $this->underscoreToCamelCase($f, true);
            if (method_exists($this, $filterMethod) ){     
                $exp = $this->{$filterMethod}($backend);
                
                if(is_string($exp)) {
                    // Clean up string
                    $exp = str_replace(["\n","\r"], '', $exp);
                    $exp = $this->normalizeWhiteSpace($exp);
                }
                
                $filterExpressions[$f] = $exp;
            }       
        }
        
        return $filterExpressions;
    }
    
    
    /**
     * Return an array of the fields that can be use to boots
     * each Recomentation setting.
     *
     * @return array
     */
    abstract protected function getWeightFeatures();
    
    /**
     * Return an array of all active boost fields
     * @return array
     */
    public function getActiveWeightFeature()
    {
        $feature = $this->getSettingValue('weight_by', null);
    	$feature = ($feature == '') ? null : $feature;
    	return $feature;
    }    
    
    /**
     * Return an array where key is the field name that is related to other Recommendation Item
     * and the value is the name full namesapce of the Recommendation Item 
     * @return array
     * eg. [ 'users' => DMA\Recommendations\Classes\Items\UserItem ]
     */
    public function getItemRelations()
    {
        return [];
    }
    
    /**
     * Return an array where the key is the feature name
     * and the value is are the rules use to determinate
     * if an item should always present in the result regardless
     * if this is part of the recomendation or not
     * 
     * @return array
     * 
     * eg. [ 'priority' => 11 ]
     */
    public function getStickyItemRules()
    {
        // TODO : implement operators 
        // [ 'priority' => ['gte' => 10 ]]
        return [];
    }
    
    /**
     * Return an array of events namespaces that will be bind
     * to keep updated this Recommendation Item in each engine backend.
     * 
     * An event can define a custom Clouser with custom logic. If a Clouser
     * is not given the engine will generate a generic Clouser that look for register
     * October databse models and use the Recommendation Item of it if register. 
     * 
     * 
     * Eg. 
     * public function getUpdateEvents()
     * {
     *    // Create a reference to this Item so it can be use within the event clouser
     *    $item = $this;
     *    return [
     *		      'friends.activityUpdated',
     *		      'friends.activityCompleted' => function($user, $activity) use ($item){
     *		          // $this is a instance reference to the active engine
     *                $this->update($activity);
     *		          $this->update($user);
     *		      
     *		      }
     *    ];
     *  }
     * 
     * @return Array
     */
    abstract public function getUpdateEvents();
    
}
