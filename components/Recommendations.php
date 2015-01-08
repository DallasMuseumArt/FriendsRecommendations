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
    const EMPTY_NOTHING = 'nothing';
    const EMPTY_WEIGHT  = 'weight';
    const EMPTY_POPULAR = 'popular';
    const EMPTY_CUSTOM  = 'custom';
  
    public function componentDetails()
    {
        return [
            'name'        => 'Recomendation',
            'description' => 'Provide a listing of recomendations to the current user'
        ];
    }
    
    public function defineProperties()
    {
        # Temporal solution checkbox are not working
        $opts = array_keys(Recommendation::getRegisterItems(true));
        $opts = implode(', ', array_map('ucwords', $opts));

        return [
            'recommend' => [
                'title'       => 'Recommend',
                'description' => 'Comma separated items to recommend. Options are: [ ' . $opts . ' ]',  
                'type'        => 'string',
                'default'     => $opts,

            ], 
            'limit' => [
                    'title'             => 'Recommend limit',
                    'description'       => 'Number maximum recommend itmes to return. If empty or 0 global limit will be use.',
                    'type'              => 'string',
                    'validationPattern' => '^(\d+|)$',
                    'validationMessage' => 'Limit should be a positive integer',
                    'default'           => ''
            
            ],                
            'viewTemplate' => [
                'title'       => 'View template',
                'description' => 'Comma separated items to recommend. Options are: [ ' . $opts . ' ]',
                'type'        => 'string',
                'default'     => 'dma.friends::%sPreview',
            ],
            'ifEmpty' => [
                'title'       => 'When empty return items',
                'description' => 'If not recomendation are returned Recommendation Items sort by:',
                'type'        => 'dropdown',
                'default'     => 'weight'
                 
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
        $user = $this->getUser();
        if( $items = $this->property('recommend')){
                        
            // Use define limit or global limit
            $limit = $this->property('limit');
            if (empty($limit) || $limit <= 0){
                $limit = null;
            }
                       
            $items = explode(',', $items);
            $items = array_map(function($e){ return strtolower(trim($e)); }, $items);
            
            $result = Recommendation::suggest($user, $items, $limit);
            
            // Fill empty result if required
            $ifEmpty = $this->property('ifEmpty');
            
            if ($ifEmpty != self::EMPTY_NOTHING ){
                
                // Find Items with empty results
                $emptyKeys = [];
                foreach( $result as $key => $grp){
                    if($grp->count() == 0){
                        $emptyKeys[] = $key;
                    }
                }
                
                switch($ifEmpty){
                    case self::EMPTY_WEIGHT:
                        $fill = Recommendation::getItemsByWeight($user, $emptyKeys, $limit);
                        $result = $result->merge($fill);
                        break;
    
                    case self::EMPTY_POPULAR:
                        $fill = Recommendation::getTopItems($user, $emptyKeys, $limit);
                        $result = $result->merge($fill);
                        break;
    
                   case self::EMPTY_CUSTOM:
                        // TODO : needs to be implemented
                        break;
                        
                    case self::EMPTY_NOTHING:
                    default:
                        break;
                }
                
            }
            
            
            return $result;
        }
        return [];
        
    }

    
    protected function prepareVars($vars = [])
    {
       
        $renders = [];
        foreach($this->getRecomendations() as $item => $grp) {
            foreach($grp as $model){
                $viewname = sprintf($this->property('viewTemplate'), $item);
                $renders[$item][]  = View::make($viewname, ['model' => $model, 'class' => 'col-md-3 col-sm-4'])->render();
            }
            $this->page['recommendations'] = $renders;

        }
        
        
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