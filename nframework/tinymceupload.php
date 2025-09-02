<?php
require 'include.php';

  /***************************************************
   * Only these origins are allowed to upload images *
   ***************************************************/



/*  if (isset($_SERVER['HTTP_ORIGIN'])) {


    // same-origin requests won't set an origin. If the origin is set, it must be valid.
    if (in_array($_SERVER['HTTP_ORIGIN'], $accepted_origins)) {
      header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    } else {
      header("HTTP/1.1 403 Origin Denied");
      return;
    }
  }*/

  // Don't attempt to process the upload on an OPTIONS request
  if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    return;
  }

if (isset($_SESSION['tinymceup'][$_GET['_id']]))
  /*********************************************
   * Change this line to set the upload folder *
   *********************************************/
  $imageFolder = $_SESSION['tinymceup'][$_GET['_id']]['mediadir'];


  reset ($_FILES);
  $temp = current($_FILES);
  if (is_uploaded_file($temp['tmp_name'])){
    /*
      If your script needs to receive cookies, set images_upload_credentials : true in
      the configuration and enable the following two headers.
    */
    // header('Access-Control-Allow-Credentials: true');
    // header('P3P: CP="There is no P3P policy."');

    // Sanitize input
    if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
        header("HTTP/1.1 400 Invalid file name.");
        return;
    }

    // Verify extension
    if (!in_array(strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION)), array("gif", "jpg", "png","webp"))) {
        header("HTTP/1.1 400 Invalid extension.");
        return;
    }
	if(!file_exists($imageFolder)){
		mkdir($imageFolder,0777,true);
	}
    // Accept upload if there was no origin, or if it is an accepted origin
    $filetowrite = $imageFolder . $temp['name'];
    move_uploaded_file($temp['tmp_name'], $filetowrite);

    // Determine the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "https://" : "http://";
//    $baseurl = $protocol . $_SERVER["HTTP_HOST"] . rtrim(dirname($_SERVER['REQUEST_URI']), "/") . "/";
	$baseurl= $_SESSION['tinymceup'][$_GET['_id']]['baseurl'];

    // Respond to the successful upload with JSON.
    // Use a location key to specify the path to the saved image resource.
    // { location : '/your/uploaded/image/file'}
    echo json_encode(array(
    	//'imagefolder'=>$imagefolder,
    	//'base'=>$baseurl,
    	//'fileto'=>$filetowrite,
    	'location' => basename($filetowrite)));
  } else {
    // Notify editor that the upload failed
    header("HTTP/1.1 500 Server Error");
  }
?>
