<?php
//// Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        //header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }
    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    }

chdir("../server");

require_once('system/includes.php');
require_once('libs/Slim/Slim.php');

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
$app->config('debug', true);


//===========================================================
class PdoStorageWithEmailVerification extends OAuth2\Storage\Pdo {
	// Override checkClientCredentials to add a check about whether the user is verified or not
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        $stmt = $this->db->prepare(sprintf('SELECT * from %s c JOIN %s u ON c.user_id = u.user_id where c.client_id = :client_id and u.email_verified = 1',
			$this->config['client_table'],
			$this->config['user_table']
			));
        $stmt->execute(compact('client_id'));
        $result = $stmt->fetch();

        // make this extensible
        return $result && $result['client_secret'] == $client_secret;
    }
}
$authenticateForRole = function () 
{
	//global $conOptions;
	$_dsn = diyConfig::read('db.dsn');
	$_username = diyConfig::read('db.username');
	$_password = diyConfig::read('db.password');
	$storage = new PdoStorageWithEmailVerification(array('dsn' => $_dsn, 'username' => $_username, 'password' => $_password));
	//$storage = new OAuth2\Storage\Pdo(array('dsn' => $_dsn, 'username' => $_username, 'password' => $_password));
	$server = new OAuth2\Server($storage);
	$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage), array(
			'allow_credentials_in_request_body => true'
		)); 

	$cryptoStorage = new OAuth2\Storage\CryptoToken($storage);
	$server->addStorage($cryptoStorage, "access_token");
		
	$cryptoResponseType = new OAuth2\ResponseType\CryptoToken($storage);
	$server->addResponseType($cryptoResponseType);
	return $server;

};

$diy_storage = function () 
{
	//global $conOptions;
	$_dbfile = diyConfig::read('db.file');
 	$db = new PDO(sprintf('sqlite:%s', $_dbfile));
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	return $db;
};

$diy_exception = function () 
{
 	$exception = new CustomException();
	return $exception;
};

//=========================  POST ==================================

/**
*
* @SWG\Resource(
*   apiVersion="0.1",
*   swaggerVersion="2.0",
*   basePath="https://arduino.os.cs.teiath.gr/api",
*   resourcePath="/token",
*   description="Get token",
*   produces="['application/json']"
* )
*/

/**
 * @SWG\Api(
 *   path="/token",
 *   @SWG\Operation(
 *     method="POST",
 *     summary="Get token",
 *     notes="epistrefei token. To token chriasete gia olous tous porous tou api",
 *     type="result",
 *     nickname="token",
 *     @SWG\Parameter(
 *       name="grant_type",
 *       description="access type p.x. client_credentials",
 *       required=true,
 *       type="text",
 *       paramType="query"
 *     ),
 *     @SWG\Parameter(
 *       name="client_id",
 *       description="your username",
 *       required=true,
 *       type="text",
 *       paramType="query"
 *     ),
 *     @SWG\Parameter(
 *       name="client_secret",
 *       description="your password",
 *       required=true,
 *       type="text",
 *       paramType="query"
 *     ),
 *     @SWG\ResponseMessage(code=200, message="Επιτυχία", responseModel="Success"),
 *     @SWG\ResponseMessage(code=500, message="Αποτυχία", responseModel="Failure")
 *   )
 * )
 *
     */

 /**
 *
 * @SWG\Model(
 *              id="result",
 *                  @SWG\Property(name="access_token",type="text",description="access_token")
 * )
 *                  @SWG\Property(name="expires_in",type="integer",description="time expires")
 *                  @SWG\Property(name="token_type",type="string",description="token_type")
 *                  @SWG\Property(name="scope",type="string",description="se poia scopes aniki o user")
 */

$app->post('/token', function () use ($authenticateForRole)  {
	$server = $authenticateForRole();
	$rr =  $server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
	return $rr;
});




/*Directories that contain api POST/GET*/
$diy_classesDir = array (
    '../api/post/',
    '../api/get/',
    '../api/put/',
    '../api/delete/'
);
foreach ($diy_classesDir as $directory) {
        foreach (glob("$directory*.php") as $__filename){
            require_once ($__filename);
        }
}


//=========================  HELPER ==================================
//function not found
$app->notFound(function () use ($app) 
{
    $controller = $app->environment();
    $controller = substr($controller["PATH_INFO"], 1);

    try
    {
       if ( !in_array( strtoupper($app->request()->getMethod()), array(MethodTypes::GET, MethodTypes::POST, MethodTypes::PUT, MethodTypes::DELETE)))
            throw new Exception(ExceptionMessages::MethodNotFound, ExceptionCodes::MethodNotFound);
        else
            throw new Exception(ExceptionMessages::FunctionNotFound, ExceptionCodes::FunctionNotFound);
    } 
    catch (Exception $e) 
    {
        $result["status"] = $e->getCode();
        $result["message"] = "[".$app->request()->getMethod()."][".$controller."]:".$e->getMessage();
    }

    echo toGreek( json_encode( $result ) ); 

});

$app->run();

//=========================================================================

function PrepareResponse()
{
    global $app;

    $app->contentType('application/json');
    $app->response()->headers()->set('Content-Type', 'application/json; charset=utf-8');
    $app->response()->headers()->set('X-Powered-By', 'DIYiot Tools');
    $app->response()->setStatus(200);
}


function UrlParamstoArray($params)
{
    $items = array();
    foreach (explode('&', $params) as $chunk) {
        $param = explode("=", $chunk);
        $items = array_merge($items, array($param[0] => urldecode($param[1])));
    }
    return $items;

}

function loadParameters()
{
    global $app;

    if ($app->request->getBody())
    {
        if ( is_array( $app->request->getBody() ) )
            $params = $app->request->getBody();
        else if ( json_decode( $app->request->getBody() ) )
            $params = get_object_vars( json_decode($app->request->getBody(), false) );
        else
            $params = UrlParamstoArray($app->request->getBody());
    }
    else
    {
        if ( json_decode( key($_REQUEST) ) )
            $params = get_object_vars( json_decode(key($_REQUEST), false) );
        else
            $params = $_REQUEST;
    }
    
    // array to object
    //$params = json_decode (json_encode ($params), FALSE);

    return $params;
}

function replace_unicode_escape_sequence($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}

function toGreek($value)
{
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 'replace_unicode_escape_sequence', $value ? $value : array());
}

function diy_validate64($buffer)
{
  $VALID  = 1;
  $INVALID= 0;

  $p    = $buffer;
  $len  = strlen($p);

  for($i=0; $i<$len; $i++)
  {
     if( ($p[$i]>="A" && $p[$i]<="Z")||
         ($p[$i]>="a" && $p[$i]<="z")||
         ($p[$i]>="/" && $p[$i]<="9")||
         ($p[$i]=="+")||
         ($p[$i]=="=")||
         ($p[$i]=="\x0a")||
         ($p[$i]=="\x0d")
       )
       continue;
     else
       return $INVALID;
  }  //fall through if all ok
return $VALID;
};

?>
