<?php namespace DMA\Recommendations\API\Resources;

use Response;
use Recommendation;
use RainLab\User\Models\User;
use DMA\Friends\Classes\API\BaseResource;
use DMA\Friends\Classes\API\AdditionalRoutesTrait;

class RecommendationResource extends BaseResource {
    
    use AdditionalRoutesTrait;
    
    public function __construct()
    {
        // Add additional routes to Activity resource
        $this->addAdditionalRoute('suggest', 'suggest/{item}/{user}',           ['GET']);
        $this->addAdditionalRoute('suggest', 'suggest/{item}/{user}/{limit}',   ['GET']);
    }
    
    /**
     * // TODO found a way to tell 
     * 
     * @SWG\Get(
     *     path="recommendations/suggest/{item}/{user}",
     *     description="Returns user recomendations",
     *     summary="Return user recommendations by user",
     *     tags={ "recommendations"},
     *     
     *     
     *     @SWG\Parameter(
     *         description="Items to recommend",
     *         in="path",
     *         name="item",
     *         required=true,
     *         type="string",
     *         enum={"badge", "activity"}
     *     ),
     *
     *     @SWG\Parameter(
     *         description="ID of user to make recommendations",
     *         format="int64",
     *         in="path",
     *         name="user",
     *         required=true,
     *         type="integer"
     *     ),
     *
     *     @SWG\Response(
     *         response=200,
     *         description="Successful response",
     *         @SWG\Schema(ref="#/definitions/badge"),
     *         @SWG\Schema(ref="#/definitions/activity")
     *     ),
     *     @SWG\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @SWG\Schema(ref="#/definitions/error500")
     *     ),
     *     @SWG\Response(
     *         response=404,
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error404")
     *     )
     * )
     */
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
