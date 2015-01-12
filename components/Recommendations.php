<?php namespace DMA\Recommendations\Components;

use Auth;
use View;
use Request;
use Recommendation;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;

use System\Classes\ApplicationException;
use Illuminate\Database\Eloquent\Collection;

class Recommendations extends ComponentBase
{
    use \DMA\Recommendations\Traits\MultipleComponents;
    
    const EMPTY_NOTHING = 'nothing';
    const EMPTY_WEIGHT  = 'weight';
    const EMPTY_POPULAR = 'popular';
    const EMPTY_CUSTOM  = 'custom';
  
    public function componentDetails()
    {
        return [
            'name'        => 'Recommendation',
            'description' => 'Provide a listing of recommendations to the current user'
        ];
    }
    
    public function defineProperties()
    {
        // var_dump('defineProperties');
        return [
            'recommend' => [
                'title'             => 'Recommend',
                'description'       => 'Show recomendations for the selected item.',  
                'type'              => 'dropdown'
            ], 
            'viewTemplate' => [
                'title'             => 'View template',
                'description'       => 'Recommendation Item template. It could be any view in OctoberCMS. It could be also a string format where %s is the Recommendation Item key.',
                'type'              => 'string',
                'default'           => 'dma.friends::%sPreview',
            ],
            'viewClass' => [
                'title'             => 'View template CSS class',
                'description'       => 'Extra CSS classes to add to the given view template.',
                'type'              => 'string',
                //'default'           => 'col-md-3, col-sm-4'     
            ],        
            'ifEmpty' => [
                'title'             => 'When empty return items',
                'description'       => 'If not recomendation are returned Recommendation Items sort by one of the given options.',
                'type'              => 'dropdown',
                'default'           => 'weight'
                 
            ],                
            'limit' => [
                'title'             => 'Recommend limit',
                'description'       => 'Number maximum of Recommendation Items to be return. If empty or 0 global limit will be use.',
                'type'              => 'string',
                'validationPattern' => '^(\d+|)$',
                'validationMessage' => 'Limit should be a positive integer',
                'default'           => ''
            
            ]
        ];
    }
    
    protected function getuser()
    {
        $this->user = Auth::getUser();
        return $this->user;
    }
    
    protected function getRecomendations()
    {   
        
        //var_dump('running');
        // var_dump($this->alias);
        
        $user = $this->getUser();
        $key  = $this->property('recommend');
        
        // var_dump($key);

        // Use define limit or global limit
        $limit = $this->property('limit');
        if (empty($limit) || $limit <= 0){
            $limit = null;
        }
        
        $result = Recommendation::suggest($user, [$key], $limit);
        
        // Fill empty result if required
        $ifEmpty = $this->property('ifEmpty');
        
        $recomended = array_get($result, $key, new Collection([]));
        $isEmpty = $recomended->count() == 0; 
        
        if ($ifEmpty != self::EMPTY_NOTHING && $isEmpty){
            
            switch($ifEmpty){
                case self::EMPTY_WEIGHT:
                    $result = Recommendation::getItemsByWeight($user, [$key], $limit);
                    break;

                case self::EMPTY_POPULAR:
                    $result = Recommendation::getTopItems($user, [$key], $limit);
                    break;

               case self::EMPTY_CUSTOM:
                    // TODO : needs to be implemented
                    break;
                    
                case self::EMPTY_NOTHING:
                default:
                    break;
            }
            
        }
        
        return $result[$key];

    }

    public function getItems(){
       
        
        $renders = [];
        $item           = $this->property('recommend');
        
        // Get and clean extra CSS classes
        $viewCssClass   = $this->property('viewClass'); 
                
        foreach($this->getRecomendations() as $model){
            $viewname = sprintf($this->property('viewTemplate'), $item);
            $renders[]  = View::make($viewname, ['model' => $model, 'class' => $viewCssClass])->render();
        }
          
        return $renders;
    }
    
    protected function prepareVars($vars = [])
    {
       
        // Add variables here
        
        foreach($vars as $key => $value){
            // Append or refresh extra variables
            $this->page[$key] = $value;
        }

                   
    }
    
    
    public function onRun()
    {
        // Inject CSS and JS
        //$this->addCss('components/grouprequest/assets/css/group.request.css');
        //$this->addJs('components/grouprequest/assets/js/group.request.js');
        
        // Populate page user and other variables
    	$this->prepareVars();
    
    }    
   
    ###
    # OPTIONS
    ##

    public function getRecommendOptions()
    {
        # Temporal solution checkbox are not working
        $opts = [];
        foreach(Recommendation::getRegisterItems() as $it){
            $info = $it->getDetails();
            $opts[$it->getKey()] = array_get($info, 'name', '');
        }
        return $opts;
    }
    
    public function getIfEmptyOptions()
    {
        
    	return [
    	       self::EMPTY_WEIGHT  => 'By weight',
    	       self::EMPTY_POPULAR => 'By most popular',
    	       //self::EMPTY_CUSTOM  => 'By custom code', 
    	       self::EMPTY_NOTHING => 'Do nothing'   
    	];
    }  

    
  
}