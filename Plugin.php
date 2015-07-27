<?php namespace DMA\Recommendations;

ini_set('memory_limit', '1024M');

use Event;
use System\Classes\PluginBase;
use DMA\Recommendations\Models\Settings;

/**
 * Friends Recommendation Plugin Information File
 */
class Plugin extends PluginBase
{

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Friends Recommendations',
            'description' => 'A recommendation engine for DMA Friends platform',
            'author'      => 'Dallas Museum of Art',
            'icon'        => 'icon-thumbs-up'
        ];
    }
    
    /**
     * @var array Plugin dependencies
     */
    public $require = [
        'DMA.Friends'
    ];


    /**
	 * {@inheritDoc}
     * @see \System\Classes\PluginBase::registerPermissions()
     */
    public function registerPermissions()
    {
    	return [
            'dma.recommendations.access_admin'  => ['label' => 'Manage Recommendation engine'],
    	];
    }
    
    /**
	 * {@inheritDoc}
     * @see \System\Classes\PluginBase::registerSettings()
     */
    public function registerSettings()
    {
    	return [
            'settings' => [
                'label'        => 'Recommendation Engine',
                'description'  => 'Manage Friends recommendations settings.',
                'category'     => 'Friends',
                'icon'         => 'icon-cog',
                'class'        => 'DMA\Recommendations\Models\Settings',
                'order'        => 501,
                'permissions'  => ['dma.recommendations.*'],
            ],
    	];
    }

    public function registerComponents()
    {
    	return [
        	'DMA\Recommendations\Components\Recommendations' => 'Recommendations',
    	];
    }    
        
    /**
	 * {@inheritDoc}
     * @see \System\Classes\PluginBase::boot()
     */
    public function boot()
    {

      	// Register ServiceProviders
        \App::register('\DMA\Recommendations\RecommendationServiceProvider');
 
        // Bind Item events to the active recomendation engine
        \Recommendation::bindEvents();
        
        // Register Recommendation Items specific settings 
	    Event::listen('backend.form.extendFields', function($form) {
            if (!$form->model instanceof  \DMA\Recommendations\Models\Settings) return;
            if ($form->getContext() != 'update') return;

            $extra = [];

            // ITEM SETTINGS
            foreach(\Recommendation::getRegisterItems(false) as $it){
                if($it->adminEditable){
                    $fields = $it->getPluginSettings();
                    if(is_array($fields)){
                        foreach($fields as $key => $opts){
                            $info = $it->getDetails();
                            $tab = $info['name'];
                            $opts['tab'] = $tab;
                            $extra[$key] = $opts;
                        }
                    }
                }
            }
            
            
            // BACKEND SETTNGS
            $engine = \Recommendation::getActiveBackend();
            $fields = $engine->getPluginSettings();
            if(is_array($fields)){
            	foreach($fields as $key => $opts){
            	    //$info = $engine->getDetails();
            		$opts['tab'] = 'Engine';//$info['name'];
            		$extra[$key] = $opts;
            	}
            } 

                       
            $form->addTabFields($extra);
	    });  
    }
    

    public function register()
    {
    	// Commands for syncing wordpress data
    	$this->registerConsoleCommand('populate-engine',   'DMA\Recommendations\Commands\PopulateEngineCommand');
    	$this->registerConsoleCommand('clean-engine',      'DMA\Recommendations\Commands\CleanEngineCommand');
    	$this->registerConsoleCommand('update-item',       'DMA\Recommendations\Commands\UpdateItemCommand');
    }
    
    /**
     * Register Friends API resource endpoints
     *
     * @return array
     */
    public function registerFriendAPIResources()
    {
        return [
                'recommendations'      => 'DMA\Recommendations\API\Resources\RecommendationResource',
        ];
    }
    
}
