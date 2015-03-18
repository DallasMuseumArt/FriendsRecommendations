<?php namespace DMA\Recommendations\API\Resources;

use Response;
use Controller;
use Recommendation;
use RainLab\User\Models\User;

class RecommendationResource extends Controller {
    
    public function getSuggest($item, $user, $limit=null)
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
                
                $data = $result[$item];
                
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
