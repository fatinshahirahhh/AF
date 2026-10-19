<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
$backupDir=$root.'/backups';
if(!is_dir($backupDir))mkdir($backupDir,0700,true);
$stamp=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur')))->format('Ymd-His');
$path=$backupDir.'/af-cultured-'.$stamp.'.zip';
$snapshot=$backupDir.'/snapshot-'.bin2hex(random_bytes(6)).'.sqlite';
try {
 $db=new PDO('sqlite:'.$root.'/storage/shop.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 if($db->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Database integrity check failed.');
 $db->exec('VACUUM INTO '.$db->quote($snapshot));
 $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create backup.');
 foreach(['dist','scripts'] as $folder){$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));foreach($iterator as $file){if(!$file->isFile()||$file->isLink())continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));$zip->addFile($file->getPathname(),$relative);}}
 foreach(['index.php','.htaccess','README.md','DEPLOYMENT.md','storage/.htaccess','backups/.htaccess'] as $file)if(is_file($root.'/'.$file))$zip->addFile($root.'/'.$file,$file);
 $zip->addFile($snapshot,'storage/shop.sqlite');
 if(!$zip->close())throw new RuntimeException('Could not finish backup.');
 $check=new ZipArchive();if($check->open($path,ZipArchive::CHECKCONS)!==true||$check->locateName('storage/shop.sqlite')===false||$check->locateName('dist/index.html')===false)throw new RuntimeException('Backup verification failed.');
 $count=$check->numFiles;$check->close();
 echo json_encode(['backup'=>$path,'files'=>$count,'sha256'=>hash_file('sha256',$path),'database_integrity'=>'ok','note'=>'Includes seller password hash; store privately. Active login sessions excluded.'],JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {if(is_file($snapshot))unlink($snapshot);}
