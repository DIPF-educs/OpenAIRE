<?php
###############################################################################
# Anpassung des Scripts cronjob.log2ctx.php.combined von OAS
# an die Erfordernisse des Scripts matomo_import_logs.py von OpenAire
# die Config-Datei pedocs-matomo-config.php muss im selben Verzeichnis liegen 
# wie die Datei cronjob.matomo_import_logs.py.php
#
# Option l - hier kann die zu verarbeitende Log-Datei inklusive komplettem Pfad
#            angegeben werden
# ohne Option l werden alle noch nicht verarbeiteten Logdateien aus dem 
# Verzeichnis in_folder der Config-Datei verarbeitet.
# Verarbeitete Logdateien werden in der logfiles_list.txt gelistet.
#
# Henning Hinze <hinze@dipf.de> DIPF 24.01.2020
###############################################################################
require_once(dirname(__FILE__).'/pedocs-matomo-config.php');
require_once("../pedocs_functions.php");
require_once("../class.mime_mail.php");
require_once("../class.configFile.php");
$conf = new configFile ("../pedocs.conf");
$prog_mail = $conf->value("prog_mail");
$opusmail   = $conf->value("opusmail");
# Parse command line options
$options=getopt('vl:');$verbose = 0;
$logfiles = array();
if(isset($options['v'])) $verbose = 1;
if(isset($options['l']) && file_exists($options['l'])) $logfiles[] = $options['l'];
$data_folder = $config['data_folder'];
if(!$logfiles) {
  if(file_exists($config['data_folder']."/logfiles_list.txt")) {
    $logfile_list_handle = fopen($config['data_folder']."/logfiles_list.txt","r");
    while(!feof($logfile_list_handle)) {
      $entry = fgets($logfile_list_handle);
      if(trim($entry)) $logfile_list[] = pathinfo(trim($entry),PATHINFO_BASENAME);
    }
    if($logfile_list_handle) fclose($logfile_list_handle);
  }
  if($verbose) print("Einlesen der bisherigen Logfile-List\n");
  if(!$logfile_list) $logfile_list = array();
//   print_r($logfile_list);
  $jahre = get_verzeichnis_array($config['in_folder'],"(\d\d\d\d)");
  foreach($jahre as $jahr) {
    if($jahr < $config['log_start_jahr']) {
      if($verbose) print("Das Jahr $jahr liegt vor dem Beginn der OpenAireStatistic\n");
      continue;
    }
    $monate = get_verzeichnis_array($config['in_folder']."/$jahr/","(\d\d)");
    foreach($monate as $monat) {
      if($jahr <= $config['log_start_jahr'] && $monat < $config['log_start_monat']) {
        if($verbose) print("Der Monat $monat $jahr liegt vor dem Beginn der OpenAireStatistic\n");
        continue;
      }
      foreach(glob($config['in_folder']."/$jahr/$monat/access_log_pedocs.*") as $logfile) {
  //       print($logfile-" - ".pathinfo($logfile,PATHINFO_BASENAME)." - ".substr(pathinfo($logfile,PATHINFO_BASENAME),26,2)."\n");
        if($jahr <= $config['log_start_jahr'] &&
           $monat <= $config['log_start_monat'] &&
           substr(pathinfo($logfile,PATHINFO_BASENAME),26,2) < $config['log_start_tag'])
        {
          if($verbose) print("Der Tag ".substr(pathinfo($logfile,PATHINFO_BASENAME),26,2).".".$monat.".".$jahr." liegt vor dem Beginn der OpenAireStatistic\n");
          continue;
        }
        if(in_array(pathinfo($logfile,PATHINFO_BASENAME), $logfile_list)) {
          if($verbose) print("Logfile ".pathinfo($logfile,PATHINFO_BASENAME)." wurde bereits verarbeitet\n");
          continue;
        }
        if($verbose) print("hinzugefügt zur aktuellen Logfile-List: $logfile\n");
        $logfiles[] = $logfile;
      }
    }
  }
}
// exit;
sort($logfiles);
// print_r($logfiles);
unset($logfile);
print("\n");
// exit;
passthru('source '.$config['data_folder'].'/bin/activate');
$logfile_list_handle = fopen($config['data_folder']."/logfiles_list.txt","a");
foreach($logfiles as $logfile) {
  print("wird bearbeitet: $logfile\n");
  if($verbose) print("Kopie in Current-Folder");
  copy($logfile, $config['data_folder'].$config['current-log_subfolder']."/".pathinfo($logfile,PATHINFO_BASENAME));
  if($verbose) print(" - Bearbeitung durch matomo_import_log\n");
  passthru($config['data_folder'].'/bin/python2.7 '.
              $config['data_folder'].'/matomo_import_logs.py '.
              $config['data_folder'].$config['current-log_subfolder']." > ".$config['data_folder']."/protokoll.txt", $return_var);
  if($return_var) print(" nicht");
  print(" erfolgreich ");
  $new_filename = pathinfo($logfile,PATHINFO_BASENAME);$i = 0;
  if(!$return_var) {
    while(file_exists($config['data_folder'].$config['success-log_subfolder']."/".$new_filename.($i?".$i":""))) {
      $i++;
    }
    if($verbose) print(" - Verschieben in die Success-Folder");
    rename($config['data_folder'].$config['current-log_subfolder']."/".pathinfo($logfile,PATHINFO_BASENAME),
           $config['data_folder'].$config['success-log_subfolder']."/".$new_filename.($i?".$i":""));
    if($verbose) print(" - Anhängen an die Logfile-List");
    fputs($logfile_list_handle,$logfile."\n");
  }
  else {
    while(file_exists($config['data_folder'].$config['error-log_subfolder']."/".$new_filename.($i?".$i":""))) {
      $i++;
    }
    if($verbose) print(" - Verschieben in die error-Folder");
    rename($config['data_folder'].$config['current-log_subfolder']."/".pathinfo($logfile,PATHINFO_BASENAME),
           $config['data_folder'].$config['error-log_subfolder']."/".$new_filename.($i?".$i":""));
  }
  $OpenAire_logmail = new mime_mail;
  $OpenAire_logmail->from = $opusmail;
  $OpenAire_logmail->to = $prog_mail;
  $OpenAire_logmail->headers = "Errors-To: $prog_mail";
  $OpenAire_logmail->subject = "OpenAire-Statistik-Protokoll für ".$new_filename.($i?".$i":"");
  $OpenAire_logmail->add_attachment(file_get_contents($config['data_folder']."/protokoll.txt"),
                                "OpenAire-Statistik-Protokoll.txt",
                                "text/txt");
//   $OpenAire_logmail->add_attachment(file_get_contents($config['data_folder']."/Matomo_import.log"),
//                                 "OpenAire-Matomo_import.log",
//                                 "text/txt");
  $OpenAire_logmail->body = $OpenAire_logmail->subject.
                          "\n\nDies ist eine automatisch erstellte Mail. ".
                          "Bitte antworten Sie nicht direkt an den Absender dieser Mail.";
  if($OpenAire_logmail->send())
    print("Protokoll versendet an ".$prog_mail."\n");
  else print("Protokoll konnte nicht an ".$prog_mail." versendet werden.\n");
  unset($OpenAire_logmail);
  print("\n");
}
if($logfile_list_handle) fclose($logfile_list_handle);

?>