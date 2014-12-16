<?php namespace DMA\Recommendations\Components;

use Auth;
use View;
use Request;
use Recommendation;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;

use System\Classes\ApplicationException;

class Recommendations extends ComponentBase
{
  
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
            'recomend' => [
                'title'       => 'Recomend',
                'description' => 'Comma separated items to recommend. Options are: [ ' . $opts . ' ]',  
                'type'        => 'string',
                'default'     => $opts,

            ],                       
            'viewTemplate' => [
                'title'       => 'View template',
                'description' => 'Comma separated items to recommend. Options are: [ ' . $opts . ' ]',
                'type'        => 'string',
                'default'     => 'dma.friends::%sPreview',
            ],
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
        if( $items = $this->property('recomend')){
            $items = explode(',', $items);
            $items = array_map('trim', $items);
            return Recommendation::suggest($user, $items);
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

    
  
}