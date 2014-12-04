<?php namespace DMA\Recomendations;

use Event;
use System\Classes\PluginBase;

/**
 * recomendations Plugin Information File
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
            'name'        => 'Recomendations',
            'description' => 'A simple recomendation engine for DMA Friends platform',
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
    	'dma.recomendations.access_admin'  => ['label' => 'Manage Recomendations'],
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
        	'label'           => 'Recomendation engine',
        	'description'     => 'Manage Friends recomendations settings.',
        	'category'        => 'Friends',
        	'icon'            => 'icon-cog',
        	'class'           => 'DMA\Recomendations\Models\Settings',
        	'order'           => 501,
        	],
    	];
    }
        
        
    /**
	 * {@inheritDoc}
     * @see \System\Classes\PluginBase::boot()
     */
    public function boot()
    {
      	// Register ServiceProviders
        \App::register('\DMA\Recomendations\RecomendationServiceProvider');
 
        // Bind Item events to the active recomendation engine
        \Recomendation::bindEvents();
        
        // Register recomendation items specific settings 
	    Event::listen('backend.form.extendFields', function($form) {
            if (!$form->model instanceof \DMA\Recomendations\Models\Settings) return;
            if ($form->getContext() != 'update') return;

            $extra = [];
            
            foreach(\Recomendation::getRegisterItems() as $it){
                if($it->adminEditable){
                    $fields = $it->getPluginSettings();
                    if(is_array($fields)){
                        foreach($fields as $key => $opts){
                            $tab = $it->info['name'];
                            $opts['tab'] = $tab;
                            $extra[$key] = $opts;
                        }
                    }
                }
            }
                        
            $form->addTabFields($extra);
	    });  
    }
    


}
