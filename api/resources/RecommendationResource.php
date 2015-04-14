<?php namespace DMA\Recommendations\API\Resources;

use Response;
use Controller;
use Recommendation;
use RainLab\User\Models\User;

use DMA\Friends\Classes\API\AdditionalRoutesTrait;

class RecommendationResource extends Controller {
    
    use AdditionalRoutesTrait;
    
    public function __construct()
    {
        // Add additional routes to Activity resource
        $this->addAdditionalRoute('suggest', 'suggest/{item}/{user}',           ['GET']);
        $this->addAdditionalRoute('suggest', 'suggest/{item}/{user}/{limit}',   ['GET']);
    }
    
    
    public function suggest($item, $user, $limit=null)
    {
        try{
            $item = strtolower($item);
            $user = User::find($user);
            $result = [];
            if (!is_null($user)){
                $result = Recommendation::suggest($user, [$item], $limit);
            
                $transformer = array_get([
                    'badge'     => '\DMA\Friends\API\Transformers\BadgeTransformer',
                    'activity'  => '\DMA\Friends\API\Transformers\ActivityTransformer'
                ], $item, null);
                
                // Check if result is empty, this could happend because the user doesn't have
                // any activity recorded yet. If that is the case get Items by weight
                if (count(array_get($result, $item, [])) == 0){
                    $result = Recommendation::getItemsByWeight($user, [$item], $limit);
                }
                
                $data = array_get($result, $item, []);
                
                if (!is_null($transformer)){
                    $data = Response::api()->withCollection($data, new $transformer);
                }
                
                return $data;
            }
            
            return Response::api()->errorNotFound('User not found');
        } catch(Exception $e) {
            return Response::api()->errorInternalError($e->getMessage());   
        }
        
    }

    
}
