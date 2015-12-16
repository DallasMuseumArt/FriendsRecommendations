<?php namespace DMA\Recommendations\API\Resources;

use Response;
use Request;
use Recommendation;
use RainLab\User\Models\User;
use DMA\Friends\Classes\API\BaseResource;
use DMA\Friends\Classes\API\AdditionalRoutesTrait;
use DMA\Recommendations\Models\Settings;
use Illuminate\Database\Eloquent\Collection;

class RecommendationResource extends BaseResource {
    
    use AdditionalRoutesTrait;
    
    const EMPTY_NOTHING = 'nothing';
    const EMPTY_WEIGHT  = 'weight';
    const EMPTY_POPULAR = 'popular';
    
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
     *     @SWG\Parameter(
     *         description="If not recommendation are returned Recommendation Items sort by one of the given options",
     *         in="query",
     *         name="if-empty",
     *         required=false,
     *         type="string",
     *         enum={"nothing", "popular", "weight"}
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
            $ifEmpty = array_get(Request::all(), 'if-empty', self::EMPTY_POPULAR);
            
            $limit = (is_null($limit)) ? Settings::get($limit, 5): $limit;
            $item = strtolower($item);
            $user = User::find($user);
            $result = [];
            if (!is_null($user)){
                $result = Recommendation::suggest($user, [$item], $limit);
            
                $transformer = array_get([
                    'badge'     => '\DMA\Friends\API\Transformers\BadgeTransformer',
                    'activity'  => '\DMA\Friends\API\Transformers\ActivityTransformer'
                ], $item, null);

                
                // Extend recomendations with TopItems or ItemsByWeight if required                
                $recomended = array_get($result, $item, new Collection([]));
                $size = $recomended->count();

                if ($ifEmpty != self::EMPTY_NOTHING && $size < $limit){
                    $fill = $limit - $size;
                    
                    switch($ifEmpty){
                        case self::EMPTY_WEIGHT:
                            $result = Recommendation::getItemsByWeight($user, [$item], $fill);
                            break;
                
                        case self::EMPTY_POPULAR:
                            $result = Recommendation::getTopItems($user, [$item], $fill);
                            break;
                
                        case self::EMPTY_NOTHING:
                        default:
                            $result = [];
                            break;
                    }
                    
                    // Fill results
                    $complete = array_get($result, $item, new Collection([]));
                    $recomended = $recomended->merge($complete);
                    $data = $recomended->unique();
                
                }else{
                    $data = array_get($result, $item, []);
                }
                
                
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
