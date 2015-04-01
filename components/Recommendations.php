<?php namespace DMA\Recommendations\Components;

use Auth;
use View;
use Request;
use Recommendation;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use System\Classes\ApplicationException;
use Illuminate\Database\Eloquent\Collection;
use DMA\Recommendations\Models\Settings;
use DMA\Friends\Classes\BadgeManager;
use DMA\Friends\Models\Badge;
use DMA\Friends\Models\Bookmark;

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
    
    protected function getRecommendations($filterstr = null)
    {   
               
        $user = $this->getUser();
        $key  = $this->property('recommend');
        
        // Use define limit or global limit
        $limit = $this->property('limit');
        if (empty($limit) || $limit <= 0){
       		$limitSetting = $key . '_max_recomendations'; 
       		$limit = Settings::get($limitSetting);
            $limit = (int)$limit;
        }
        
        $result = Recommendation::suggest($user, [$key], $limit);
        
        // Fill empty result if required
        $ifEmpty = $this->property('ifEmpty');
        
        $recomended = array_get($result, $key, new Collection([]));
        $size = $recomended->count();
        
        if ($ifEmpty != self::EMPTY_NOTHING && $size < $limit){
            $fill = $limit - $size;
            
            switch($ifEmpty){
                case self::EMPTY_WEIGHT:
                    $result = Recommendation::getItemsByWeight($user, [$key], $fill);
                    break;

                case self::EMPTY_POPULAR:
                    $result = Recommendation::getTopItems($user, [$key], $fill);
                     break;

               case self::EMPTY_CUSTOM:
                    // TODO : needs to be implemented
                    break;
                    
                case self::EMPTY_NOTHING:
                default:
                    break;
            }
            
            // Fill results
            $complete = array_get($result, $key, new Collection([]));
            $recomended = $recomended->merge($complete);
            $result[$key] = $recomended->unique();
            
        }

        // Currently the recommendation engine can handle a list of keys,
        // but this function, getRecommendations, assumes a single key. When
        // this function can handle multiple keys, the statements below will
        // need to change.
        if ($key == 'activity') {
            $this->page['activities'] =  $result[$key];
        }
        else if ($key == 'badge') {
            $this->page['badges'] = $result[$key];
        }
    }

    public function onRun()
    {
        // Inject CSS and JS
        //$this->addCss('components/grouprequest/assets/css/group.request.css');
        //$this->addJs('components/grouprequest/assets/js/group.request.js');

        $this->getRecommendations();
    
    }    

    // We're extending this to enable compatibility with ActivityFilters component
    public function onUpdate()
    {
        $filter = post('filter');
        $this->getRecommendations($filter);
        $this->page['filter'] = $filter;

        if ($key == 'activity') {
            $template =  '@activitylist.htm';
        }
        else if ($key == 'badge') {
            $template =  '@badgelist.htm';
        }

        return [
            '#recommendations' => $this->renderPartial($template),
        ];
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

    /*
    * TODO : The following methods onRenderBadge, onBookmarkAdd,
    * onBookmarkRemove, loadBadge, should not be here.
    * they provide a specific bussines logic. A solution
    * will be create a badge component so they have they our controller 
    * so the AJAX handlers can work. 
    * This component should use the trait \DMA\Recommendations\Traits\MultipleComponents
    */
    public function onRenderBadge()
    {
        $id = post('id');
        $badge = Badge::find($id);
        return BadgeManager::render($this, $badge);
    }

    public function onBookmarkAdd()
    {
        $badge = $this->loadBadge();
        $user = Auth::getUser();

        Bookmark::saveBookmark($user, $badge);
        return [
            '.bookmark' => View::make('dma.friends::onBookmarkRemove', ['id' => $badge->id])->render(),
        ];
    }

    public function onBookmarkRemove()
    {
        $badge = $this->loadBadge();
        $user = Auth::getUser();

        Bookmark::removeBookmark($user, $badge);
        return [
            '.bookmark' => View::make('dma.friends::onBookmarkAdd', ['id' => $badge->id])->render(),
        ];
    }

    protected function loadBadge()
    {
        $id = post('id');
        return Badge::find($id);
    }
  
}