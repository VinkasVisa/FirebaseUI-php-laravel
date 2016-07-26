<?php

namespace Vinkas\Visa\Traits;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Auth;
use App\User;

/**
* Class AuthenticatesUsers.
*/
trait AuthenticatesUsers
{

  public function getAuth(Request $request) {
    return view('vinkas.visa.auth');
  }

  public function postAuth(Request $request) {
    $data = $request->all();
    $validator = $this->validator($data);
    if ($validator->fails())
    return $this->onFail($validator->errors()->first());

    JWT::$leeway = 8;
    $content = file_get_contents("https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com");
    $kids = json_decode($content, true);
    $jwt = JWT::decode($request->input('id_token'), $kids, array('RS256'));
    $fbpid = config('firebase.project_id');
    $issuer = 'https://securetoken.google.com/' . $fbpid;
    if($jwt->aud != $fbpid)
    return $this->onFail('Invalid audience');
    elseif($jwt->iss != $issuer)
    return $this->onFail('Invalid issuer');
    elseif(empty($jwt->sub))
    return $this->onFail('Invalid user');
    else {
      $uid = $jwt->sub;
      $user = $this->visaLogin($uid, $request);
      if($user)
      return response()->json(['success' => true, 'redirectTo' => $this->redirectPath()]);
      else
      return $this->onFail('Error');
    }
  }

  protected function onFail($message) {
    return response()->json(['success' => false, 'message' => $message]);
  }

  protected function visaLogin($uid, $request) {
    $user = User::where('id', $uid)->first();

    if($user == null)
    $this->visaRegister($uid, $request);

    $remember = $request->has('remember') ? $request->input('remember') : false;
    return Auth::loginUsingId($uid, $remember);
  }

}
