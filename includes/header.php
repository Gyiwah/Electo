<!DOCTYPE html>
<html>
<head>
  	<meta charset="utf-8">
  	<meta http-equiv="X-UA-Compatible" content="IE=edge">
  	<title><?php echo isset($page_title) ? escape_html($page_title) . ' &bull; ' : ''; ?>Electo &bull; Voting System</title>
  	<meta name="description" content="Electo - Secure, democratic digital voting platform powered by GAF Tech">
  	<!-- Mobile-First Responsive Viewport -->
  	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
  	<!-- Progressive Web App Meta Tags -->
  	<meta name="theme-color" content="#1e3a8a">
  	<meta name="mobile-web-app-capable" content="yes">
  	<meta name="apple-mobile-web-app-capable" content="yes">
  	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  	<meta name="apple-mobile-web-app-title" content="Electo">
  	<link rel="manifest" href="manifest.json">
  	<link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
  	<link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
  	<link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
  	<link rel="shortcut icon" href="favicon/favicon.ico">
  	<!-- Bootstrap 3.3.7 -->
  	<link rel="stylesheet" href="bower_components/bootstrap/dist/css/bootstrap.min.css">
    <!-- iCheck for checkboxes and radio inputs -->
    <link rel="stylesheet" href="plugins/iCheck/all.css">
  	<!-- DataTables -->
    <link rel="stylesheet" href="bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
  	<!-- Font Awesome -->
  	<link rel="stylesheet" href="bower_components/font-awesome/css/font-awesome.min.css">
  	<!-- Theme style -->
  	<link rel="stylesheet" href="dist/css/AdminLTE.min.css">
  	<!-- AdminLTE Skins. Choose a skin from the css/skins
       folder instead of downloading all of them to reduce the load. -->
  	<link rel="stylesheet" href="dist/css/skins/_all-skins.min.css">
  	<!-- Modern SaaS Design Theme -->
  	<link rel="stylesheet" href="dist/css/modern-theme.css">
  	<link rel="stylesheet" href="dist/css/toast-notifications.css">

  	<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  	<!--[if lt IE 9]>
  	<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  	<![endif]-->

  	<!-- Google Font -->
  	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

  	<style>
  		.mt20{
        margin-top: 20px;
      }
      .title{
        font-size: 50px;
      }
      #candidate_list{
        margin-top:20px;
      }

      #candidate_list ul{
        list-style-type:none;
      }

      #candidate_list ul li{ 
        margin:0 30px 30px 0; 
        vertical-align:top
      }

      .clist{
        margin-left: 20px;
      }

      .cname{
        font-size: 25px;
      }

      .votelist{
        font-size: 17px;
      }
  	</style>		
</head>