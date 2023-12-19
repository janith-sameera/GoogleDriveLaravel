<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;

class GoogleDriveController extends Controller
{
    public $gClient;

    function __construct(){

        $this->gClient = new \Google_Client();

        $this->gClient->setApplicationName('YOUR APPLICATION NAME'); // ADD YOUR AUTH2 APPLICATION NAME (WHEN YOUR GENERATE SECRATE KEY)
        $this->gClient->setClientId('YOUR CLIENT ID');
        $this->gClient->setClientSecret('YOUR CLIENT SECRET ID');
        $this->gClient->setRedirectUri('YOUR REDIRECT URL');
        $this->gClient->setDeveloperKey('GOOGLE PROJECT API KEY');
        $this->gClient->setScopes(array(
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/drive'
        ));

        $this->gClient->setAccessType("offline");

        $this->gClient->setApprovalPrompt("force");
    }

    public function googleLogin(Request $request)  {

        $google_oauthV2 = new \Google_Service_Oauth2($this->gClient);

        if ($request->get('code')){

            $this->gClient->authenticate($request->get('code'));

            $request->session()->put('token', $this->gClient->getAccessToken());
        }

        if ($request->session()->get('token')){

            $this->gClient->setAccessToken($request->session()->get('token'));
        }

        if ($this->gClient->getAccessToken()){

            //FOR LOGGED IN USER, GET DETAILS FROM GOOGLE USING ACCES
            $user = User::find(1);

            $user->access_token = json_encode($request->session()->get('token'));

            $user->save();

            dd("Successfully authenticated");

        } else{

            // FOR GUEST USER, GET GOOGLE LOGIN URL
            $authUrl = $this->gClient->createAuthUrl();

            return redirect()->to($authUrl);
        }
    }

    public function googleDriveFilePpload(Request $request)
    {


        $folder_name="Backup";
        $client_name="Janith";


        $service = new \Google_Service_Drive($this->gClient);

        $user= User::find(1);

        $this->gClient->setAccessToken(json_decode($user->access_token,true));

        if ($this->gClient->isAccessTokenExpired()) {

            // SAVE REFRESH TOKEN TO SOME VARIABLE
            $refreshTokenSaved = $this->gClient->getRefreshToken();

            // UPDATE ACCESS TOKEN
            $this->gClient->fetchAccessTokenWithRefreshToken($refreshTokenSaved);

            // PASS ACCESS TOKEN TO SOME VARIABLE
            $updatedAccessToken = $this->gClient->getAccessToken();

            // APPEND REFRESH TOKEN
            $updatedAccessToken['refresh_token'] = $refreshTokenSaved;

            // SET THE NEW ACCES TOKEN
            $this->gClient->setAccessToken($updatedAccessToken);

            $user->access_token=$updatedAccessToken;

            $user->save();
        }

        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'name' => $folder_name,             // ADD YOUR GOOGLE DRIVE FOLDER NAME
            'mimeType' => 'application/vnd.google-apps.folder'));

        $folder = $service->files->create($fileMetadata, array('fields' => 'id'));

        printf("Folder ID: %s\n", $folder->id);
        $currentDate = Carbon::now();
        $formattedDateTime = $currentDate->format('Y-m-d H:i:s');
        $file = new \Google_Service_Drive_DriveFile(array('name' => $formattedDateTime.'_'.$client_name,'parents' => array($folder->id)));

        $result = $service->files->create($file, array(

            'data' => file_get_contents(public_path('logo-01.png')), // ADD YOUR FILE PATH WHICH YOU WANT TO UPLOAD ON GOOGLE DRIVE
            'mimeType' => 'application/octet-stream',
            'uploadType' => 'media'
        ));

        // GET URL OF UPLOADED FILE

        $url='https://drive.google.com/open?id='.$result->id;

        dd($url);
    }
}
