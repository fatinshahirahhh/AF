<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$sessionDir=dirname(__DIR__).'/storage/sessions';
if(!is_dir($sessionDir)) mkdir($sessionDir,0700,true);
session_save_path($sessionDir);
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Strict', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
if(!session_start()) { http_response_code(500); echo json_encode(['error'=>'Unable to start a secure seller session.']); exit; }
function reply(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }
function input(): array { $raw=file_get_contents('php://input'); if(strlen($raw)>4000000) reply(['error'=>'Request too large.'],413); $data=json_decode($raw,true); if(!is_array($data)) reply(['error'=>'Invalid JSON.'],400); return $data; }
$action=$_GET['action'] ?? 'catalogue';
$method=$_SERVER['REQUEST_METHOD'];
try {
    $dir=dirname(__DIR__).'/storage';
    if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create storage.');
    $db=new PDO('sqlite:'.$dir.'/shop.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (address TEXT PRIMARY KEY, count INTEGER NOT NULL, started INTEGER NOT NULL)');
    function setting(string $key): ?string { global $db; $s=$db->prepare('SELECT value FROM settings WHERE key=?'); $s->execute([$key]); $v=$s->fetchColumn(); return $v===false?null:$v; }
    function put(string $key,string $value): void { global $db; $s=$db->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'); $s->execute([$key,$value]); }
    if($method!=='GET') {
        $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
        if($origin && parse_url($origin,PHP_URL_HOST)!==explode(':',$_SERVER['HTTP_HOST'])[0]) reply(['error'=>'Invalid request origin.'],403);
    }
    if($action==='session' && $method==='GET') reply(['authenticated'=>!empty($_SESSION['seller']), 'setupRequired'=>setting('password')===null, 'csrf'=>!empty($_SESSION['seller'])?($_SESSION['csrf']??''):null]);
    if(($action==='setup' || $action==='login') && $method==='POST') {
        $data=input(); $username=trim((string)($data['username']??'')); $password=(string)($data['password']??'');
        if($action==='setup') {
            $remote=$_SERVER['REMOTE_ADDR']??'';
            if(!in_array($remote,['127.0.0.1','::1'],true)) reply(['error'=>'Create the first seller account on this computer using localhost.'],403);
            if(strlen($username)<3 || strlen($username)>80 || strlen($password)<12 || strlen($password)>200) reply(['error'=>'Use a username of 3–80 characters and password of 12–200 characters.'],422);
            $db->exec('BEGIN IMMEDIATE');
            if(setting('password')!==null) { $db->exec('ROLLBACK'); reply(['error'=>'Seller account already exists. Please log in.'],409); }
            put('username',$username); put('password',password_hash($password,PASSWORD_DEFAULT)); $db->exec('COMMIT');
        } else {
            $address=$_SERVER['REMOTE_ADDR']??'unknown'; $s=$db->prepare('SELECT * FROM attempts WHERE address=?'); $s->execute([$address]); $attempt=$s->fetch(PDO::FETCH_ASSOC);
            if($attempt && (int)$attempt['started']>time()-900 && (int)$attempt['count']>=10) reply(['error'=>'Too many attempts. Try again in 15 minutes.'],429);
            if(!hash_equals(setting('username')??'', $username) || !password_verify($password,setting('password')??'')) {
                $count=($attempt && (int)$attempt['started']>time()-900)?(int)$attempt['count']+1:1;
                $started=$count===1?time():(int)$attempt['started'];
                $s=$db->prepare('INSERT INTO attempts VALUES(?,?,?) ON CONFLICT(address) DO UPDATE SET count=excluded.count,started=excluded.started'); $s->execute([$address,$count,$started]);
                reply(['error'=>'Incorrect username or password.'],401);
            }
            $s=$db->prepare('DELETE FROM attempts WHERE address=?'); $s->execute([$address]);
        }
        session_regenerate_id(true); $_SESSION['seller']=true; $_SESSION['csrf']=bin2hex(random_bytes(32)); reply(['authenticated'=>true,'csrf'=>$_SESSION['csrf']]);
    }
    if($action==='catalogue' && $method==='GET') {
        $catalogue=setting('catalogue');
        reply(json_decode($catalogue ?? file_get_contents(__DIR__.'/catalogue.json'),true,512,JSON_THROW_ON_ERROR));
    }
    if(empty($_SESSION['seller'])) reply(['error'=>'Please log in.'],401);
    if(!hash_equals($_SESSION['csrf']??'', $_SERVER['HTTP_X_CSRF_TOKEN']??'') || empty($_SESSION['csrf'])) reply(['error'=>'Session expired. Reload and log in again.'],403);
    if($action==='logout' && $method==='POST') { $_SESSION=[]; session_destroy(); reply(['ok'=>true]); }
    if($action==='catalogue' && $method==='PUT') {
        $data=input(); $c=$data['catalogue']??null;
        if(!is_array($c) || !is_array($c['products']??null) || count($c['products'])>250) reply(['error'=>'Invalid catalogue (maximum 250 products).'],422);
        foreach(['shopName','phone','instagram','tiktok'] as $key) if(!is_string($c[$key]??null) || strlen($c[$key])>250) reply(['error'=>'Invalid shop settings.'],422);
        if(!trim($c['shopName']) || ($c['phone']!=='' && !preg_match('/^\+?[0-9]{7,15}$/',$c['phone']))) reply(['error'=>'Enter a shop name and a valid international phone number.'],422);
        foreach(['instagram'=>'instagram.com','tiktok'=>'tiktok.com'] as $key=>$host) {
            if($c[$key]!=='' && (!filter_var($c[$key],FILTER_VALIDATE_URL) || parse_url($c[$key],PHP_URL_SCHEME)!=='https' || !in_array(strtolower((string)parse_url($c[$key],PHP_URL_HOST)),[$host,'www.'.$host],true))) reply(['error'=>'Use a valid HTTPS '.$key.' profile URL.'],422);
        }
        if(array_key_exists('homepage',$c)) {
            $h=$c['homepage'];
            if(!is_array($h)) reply(['error'=>'Invalid homepage settings.'],422);
            foreach(['announcement','heroEyebrow','heroTitle','heroDescription','heroButton','heroImage','heroAlt','collectionTitle','collectionDescription','storyTitle','storyText','storyImage','storyAlt','campaignTitle','campaignDescription'] as $key) {
                if(!is_string($h[$key]??null) || !trim($h[$key]) || mb_strlen($h[$key])>2000) reply(['error'=>'Homepage fields must contain 1–2000 characters.'],422);
            }
            $validImage=static fn($v)=>is_string($v) && preg_match('~^(assets/[a-zA-Z0-9_.-]+\.(?:svg|jpg|jpeg|png|webp)|uploads/[a-f0-9]+\.(?:jpg|png|webp))$~D',$v);
            if(!$validImage($h['heroImage']) || !$validImage($h['storyImage'])) reply(['error'=>'Upload a valid homepage image.'],422);
            if(!is_array($h['gallery']??null) || count($h['gallery'])>12) reply(['error'=>'Use up to 12 gallery photos.'],422);
            foreach($h['gallery'] as $photo) if(!is_array($photo) || !$validImage($photo['image']??null) || !is_string($photo['caption']??null) || mb_strlen($photo['caption'])>150 || !is_string($photo['alt']??null) || !trim($photo['alt']) || mb_strlen($photo['alt'])>250) reply(['error'=>'Check gallery photos, captions and descriptions.'],422);
            if(!is_array($h['featuredIds']??null) || count($h['featuredIds'])>200) reply(['error'=>'Invalid featured products.'],422);
            foreach($h['featuredIds'] as $id) if(!is_string($id)) reply(['error'=>'Invalid featured product ID.'],422);
            if(count(array_unique($h['featuredIds']))!==count($h['featuredIds'])) reply(['error'=>'Duplicate featured product.'],422);
        }
        $ids=[];
        foreach($c['products'] as $p) {
            if(!is_array($p)) reply(['error'=>'Invalid product.'],422);
            foreach(['id','name','category','description','image'] as $key) if(!is_string($p[$key]??null) || strlen($p[$key])>5000) reply(['error'=>'Invalid product '.$key.'.'],422);
            if(!trim($p['id']) || isset($ids[$p['id']]) || !trim($p['name']) || !in_array($p['category'],['Tops','Dresses','Sets'],true) || !array_key_exists('price',$p) || ($p['price']!==null && (!is_numeric($p['price']) || $p['price']<0 || $p['price']>1000000))) reply(['error'=>'Check product name, category, ID and price.'],422);
            $ids[$p['id']]=true;
            if(isset($p['quantity']) && (!is_int($p['quantity']) || $p['quantity']<0 || $p['quantity']>1000000)) reply(['error'=>'Quantity must be a whole number from 0 to 1000000, or blank if unconfirmed.'],422);
            if(array_key_exists('imageViews',$p)) {
                if(!is_array($p['imageViews']) || ($p['imageViews']['front']??null)!==$p['image']) reply(['error'=>'A front photo is required and must match the product cover.'],422);
                foreach($p['imageViews'] as $view=>$photo) if(!in_array($view,['front','back','both'],true) || !is_string($photo) || !preg_match('~^(assets/[a-zA-Z0-9_.-]+\.(?:svg|jpg|jpeg|png|webp)|uploads/[a-f0-9]+\.(?:jpg|png|webp))$~D',$photo)) reply(['error'=>'Invalid product photo view.'],422);
            }
            if(isset($p['images'])) {
                if(!is_array($p['images']) || count($p['images'])>12) reply(['error'=>'Invalid photo gallery.'],422);
                foreach($p['images'] as $photo) if(!is_string($photo) || !preg_match('~^(assets/[a-zA-Z0-9_.-]+\.(?:svg|jpg|jpeg|png|webp)|uploads/[a-f0-9]+\.(?:jpg|png|webp))$~D',$photo)) reply(['error'=>'Invalid gallery image.'],422);
            }
            foreach(['colors','sizes'] as $key) { if(!is_array($p[$key]??null) || !count($p[$key]) || count($p[$key])>30) reply(['error'=>'Products need sizes and colours.'],422); foreach($p[$key] as $v) if(!is_string($v) || !trim($v) || strlen($v)>80) reply(['error'=>'Invalid size or colour.'],422); }
            if(!preg_match('~^(assets/[a-zA-Z0-9_.-]+\.(?:svg|jpg|jpeg|png|webp)|uploads/[a-f0-9]+\.(?:jpg|png|webp))$~D',$p['image'])) reply(['error'=>'Use an uploaded product photo.'],422);
        }
        put('catalogue',json_encode($c,JSON_THROW_ON_ERROR)); reply(['ok'=>true]);
    }
    if($action==='upload' && $method==='POST') {
        $f=$_FILES['image']??null;
        if(!$f || $f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024) reply(['error'=>'Upload a JPG, PNG or WebP image under 5 MB.'],422);
        $info=getimagesize($f['tmp_name']); $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!$info || !isset($types[$info['mime']]) || $info[0]*$info[1]>24000000) reply(['error'=>'Use a valid image under 24 megapixels.'],422);
        $uploadDir=__DIR__.'/uploads'; if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
        $name=bin2hex(random_bytes(16)).'.'.$types[$info['mime']];
        if(!move_uploaded_file($f['tmp_name'],$uploadDir.'/'.$name)) throw new RuntimeException('Upload failed.');
        reply(['url'=>'uploads/'.$name]);
    }
    reply(['error'=>'Unsupported action or method.'],405);
} catch(Throwable $e) { error_log('Shop API: '.$e->getMessage()); reply(['error'=>'The shop could not save or load data. Check server storage permissions and try again.'],500); }
