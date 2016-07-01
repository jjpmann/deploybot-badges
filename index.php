<?php 


require 'vendor/autoload.php';

$request = Illuminate\Http\Request::capture();

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();


$path = __DIR__ . '/cache/';

$filestore = new \Illuminate\Cache\FileStore(
    new \Illuminate\Filesystem\Filesystem(),
    $path
);


$cache = new \Illuminate\Cache\Repository($filestore);

$account   = getenv('ACCOUNT');
$api_key    = getenv('API_KEY');

$repos = $cache->get('repos', null);

if (is_null($repos)) {

    $db = new Jaybizzle\DeployBot($api_key, $account);

    // get all users
    $repos = $db->getRepositories();
    $repos = collect($repos->entries);

    $envs = $db->getEnvironments();
    $envs = collect($envs->entries);

    $repos->each(function($repo) use ($envs) {
        $repo->envs = $envs->where('repository_id', $repo->id);
    });

    $cache->put('repos', $repos, 10);
}

$repo_id    = $request->segment(1);
$env_id     = $request->segment(2);
$badge      = $request->segment(3) === 'badge.svg';

function ver($env) {
    return substr($env->current_version, 0, 8);
}

function display($repos) {
    $repos->each(function($repo){
        echo "<h2>$repo->name: $repo->id</h2>";
        echo "<dl>";
        $repo->envs->each(function($env){
            $ver = ver($env);
            echo "<dt>{$env->name}: {$env->id}</dt><dd>{$ver}</dd>";
            //echo "<pre>" . print_r($env,true) . "</pre>";
        });
        echo "</dl>";
    });
    exit;
}

function badge($repos) {

    $env    = $repos->first()->envs->first();

    //echo "<pre>".__FILE__.'<br>'.__METHOD__.' : '.__LINE__."<br><br>"; var_dump( $env ); exit;
    
    $name   = $env->name;
    $ver    = ver($env);

    $render = new PUGX\Poser\Render\SvgRender();
    $poser = new PUGX\Poser\Poser(array($render));

    echo $poser->generate($name, $ver, '428F7E', 'plastic');
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
            badge($repos);
        }

    }
}




display($repos);


