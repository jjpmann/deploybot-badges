<?php 


require 'vendor/autoload.php';

$request = Illuminate\Http\Request::capture();

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$account    = getenv('ACCOUNT');
$api_key    = getenv('API_KEY');
$cache      = getenv('CACHE');

$repo_id    = $request->segment(1);
$env_id     = $request->segment(2);
$badge      = $request->segment(3) === 'badge.svg';
$format     = $request->query('format', 'flat');
$live       = $request->query('live');
$color      = $request->query('color', false);

function getColor($color) {
    $colors = [
        'green'     => '42CF2E',
        'blue'      => '2C2CCF',
        'pink'      => 'CF326B',
        'orange'    => 'CF6D01',
        'red'       => 'FF000F',
    ];
    if (isset($colors[$color])) {
        return $colors[$color];
    }
    return '428F7E';
}

if ($live === null) {
    $new = $request->fullUrlWithQuery(['live'=>true]);
    header("Location: {$new}",TRUE,301);
    exit;
}



$path = __DIR__ .'/'. $cache;

$filesystem =  new \Illuminate\Filesystem\Filesystem();

if ($filesystem->isWritable($path)) {
    $filestore = new \Illuminate\Cache\FileStore($filesystem, $path);

    $cache = new \Illuminate\Cache\Repository($filestore);

    $repos = $cache->get('repos', null);

    if (is_null($repos)) {
        $repos = getRepos($api_key, $account);
        $cache->put('repos', $repos, 10);
    }
} else {
    $repos = getRepos($api_key, $account);
}

function getRepos($api_key, $account) {
    $db = new Jaybizzle\DeployBot($api_key, $account);

    $repos = $db->getRepositories();
    $repos = collect($repos->entries);

    $envs = $db->getEnvironments();
    $envs = collect($envs->entries);

    $repos->each(function($repo) use ($envs) {
        $repo->envs = $envs->where('repository_id', $repo->id);
    });

    return $repos;

}

function addHeaders() {
    $dt = new DateTime('GMT');
    Header('Access-Control-Allow-Credentials:true');
    Header('Access-Control-Allow-Origin:*');
    Header('Access-Control-Expose-Headers:Content-Type, Cache-Control, Expires, Etag, Last-Modified');
    Header('Content-Type: image/svg+xml');
    Header('Cache-Control: no-cache');
    Header('Pragma: no-cache');
    Header('Expires: '. $dt->format('D, M, j Y G:i:s T'));
    Header('Etag: "' . md5(time()) . '"');
}

function ver($env) {
    return substr($env->current_version, 0, 8);
}

function display($repos) {
    $repos->each(function($repo){
        echo "<h2>$repo->name: <a href=\"/{$repo->id}\">{$repo->id}</a></h2>";
        echo "<dl>";
        $repo->envs->each(function($env) use ($repo) {
            $ver = ver($env);
            echo "<dt>{$env->name}: <a href=\"/{$repo->id}/{$env->id}/badge.svg\">{$env->id}</a></dt><dd>{$ver}</dd>";
            //echo "<pre>" . print_r($env,true) . "</pre>";
        });
        echo "</dl>";
    });
    exit;
}


function badge($repos, $format, $color) {

    $env    = $repos->first()->envs->first();

    //echo "<pre>".__FILE__.'<br>'.__METHOD__.' : '.__LINE__."<br><br>"; var_dump( $env ); exit;
    
    $name   = $env->name;
    $ver    = ver($env);

    $renderFormats = [
        new PUGX\Poser\Render\SvgRender(),
        new PUGX\Poser\Render\SvgFlatSquareRender(),
        new PUGX\Poser\Render\SvgFlatRender(),
    ];
    $poser = new PUGX\Poser\Poser($renderFormats);

    addHeaders();

    echo $poser->generate($name, $ver, $color, $format);
    exit;
}

$show = collect(['lmo.com']);

if ($repo_id) {
    $repos = $repos->filter(function($item) use ($repo_id) {
        return $item->id == $repo_id;
    });

    if ($env_id) {
        $repos = $repos->each(function($repo) use ($env_id) {
             $repo->envs = $repo->envs->filter(function($env) use ($env_id) {
                return $env->id == $env_id;
            });
        });

        if ($badge) {
            badge($repos, $format, getColor($color));
        }

    }
}




display($repos);



