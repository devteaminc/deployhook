<?php
// webhook file called by a commit to github

// debug function for outputting debug information
function debug($message)
{
	error_log($message);
	echo $message.PHP_EOL;
}

// Create a stream
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'user_agent'=>  "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)" 
	)
);
$context = stream_context_create($opts);

// contact the github meta api to get an up to date list of webhook CIDR's
$ghmeta = file_get_contents('https://api.github.com/meta', false, $context);
$meta = json_decode($ghmeta);

// match an IP to a CIDR mask
function ipCIDRCheck($ip, $cidr)
{
	list ($net, $mask) = explode('/', $cidr);
	$ipNet = ip2long($net);
	$ipMask = ~((1 << (32 - $mask)) - 1); 
	$ipIp = ip2long($ip);
	$ipIpNet = $ipIp & $ipMask;
	return ($ipIpNet == $ipNet);
}

// check if the requesting IP is a github webhook
$match = false;
foreach ($meta->hooks as $cidr) 
{
	if (ipCIDRCheck($_SERVER['REMOTE_ADDR'], $cidr)) 
	{   
		$match = true;
		break;
	}   
}

// if IP doesn't match Github or there is no payload from github then die
if (!$match || !isset($_POST['payload'])) 
{
	debug('no payload or matching webhook IP');
	die();
}

// decode github payload
$payload = json_decode($_POST['payload']);

// Init vars
$LOCAL_REPO           =	"/var/www/devteaminc.co/public";
$REMOTE_REPO          = "git@github.com:devteaminc/devteaminc.co.git";
$DESIRED_BRANCH       = "build";

// only update the site when a commit has been made to $DESIRED_BRANCH
if($payload->ref == "refs/heads/$DESIRED_BRANCH")
{
	debug('deploy started');

	// check if local repository already exists
	if (file_exists($LOCAL_REPO."/.git") == false)
	{   
		// local repository doesn't exist so perform initial clone
		debug('repository does not exist - performing a git clone');
		echo shell_exec("cd {$LOCAL_REPO} && git clone -b {$DESIRED_BRANCH} {$REMOTE_REPO} .");	
	}
	else
	{
		// Pull repository updates from Github
		debug('repository does exist - performing a git pull');
		echo shell_exec("cd {$LOCAL_REPO} && git pull");
	}

	debug('deploy complete ' . mktime());
}
else
{
	debug("$DESIRED_BRANCH not committed");
}
