<?php

/**
 *
 * ZPanel - A Cross-Platform Open-Source Web Hosting Control panel.
 * 
 * @package ZPanel
 * @version $Id$
 * @author Bobby Allen - ballen@zpanelcp.com
 * @copyright (c) 2008-2011 ZPanel Group - http://www.zpanelcp.com/
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 *
 * This program (ZPanel) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
# CRON JOB SECTION!
# Add a cronjob
function zapi_cronjob_add($file, $cronid, $timing, $phpexepath, $scriptpath) {
    $new_file = implode('', file($file));
    	$new_file .= "
# CRON ID:" . $cronid . "
" . $timing . " " . $phpexepath . " " . trim($scriptpath) . "
# END CRON ID:" . $cronid . "";
	if (ShowServerPlatform() != "Windows") {
		$new_file = str_replace("\r", "", $new_file);
	}
    $editfile = fopen($file, "w");
    if (!fwrite($editfile, preg_replace('/^[ \t]*[\r\n]+/m', '', $new_file))) {
        return false;
    } else {
        return true;
    }
    fclose($editfile);
}

# Remove a cron job

function zapi_cronjob_remove($file, $cronid) {
    $content = implode('', file($file));
    $content1 = explode("# CRON ID:" . $cronid . "", $content);
    $content2 = explode("# END CRON ID:" . $cronid . "", $content1[1], 2);
    $content = $content1[0] . $content2[1];
	if ($content != ""){
		preg_replace('/^[ \t]*[\r\n]+/m', '', $content);
	}
    $editfile = fopen($file, "w");
    if (!fwrite($editfile, $content)) {
        return false;
    } else {
        fclose($editfile);
        return true;
    }
}

# FTP (FILEZILLA) SECTION!
# Add FTP Account

function zapi_ftpaccount_add($filezilla_root, $username, $password, $zp_version, $directorytouse, $permissionset) {
    if (ShowServerPlatform() == "Windows") {
        # Add a FileZilla FTP Account	
        $filezilla_reload = "\"" . $filezilla_root . "FileZilla server.exe\" /reload-config";
        $filezilla_config = $filezilla_root . "FileZilla Server.xml";
        $content = implode('', file($filezilla_config));
        $content1 = explode("</Users>", $content);
        $content2 = explode("</FileZillaServer>", $content1[1], 2);
        $content = $content1[0] . $content2[1];
        $editfile = fopen($filezilla_config, "w");
        fwrite($editfile, $content);
        fclose($editfile);
        $new_file = implode('', file($filezilla_config));
        $new_file .= "<User Name=\"$username\">
	<Option Name=\"Pass\">" . md5($password) . "</Option>
	<Option Name=\"Group\"/>
	<Option Name=\"Bypass server userlimit\">0</Option>
	<Option Name=\"User Limit\">0</Option>
	<Option Name=\"IP Limit\">0</Option>
	<Option Name=\"Enabled\">1</Option>
	<Option Name=\"Comments\">Auto account generated by ZPanel (v." . $zp_version . ").</Option>
	<Option Name=\"ForceSsl\">0</Option>
	<IpFilter>
	<Disallowed/>
	<Allowed/>
	</IpFilter>
	<Permissions>
	<Permission Dir=\"" . $directorytouse . "\">
	$permissionset
	<Option Name=\"IsHome\">1</Option>
	<Option Name=\"AutoCreate\">0</Option>
	</Permission>
	</Permissions>
	<SpeedLimits DlType=\"0\" DlLimit=\"10\" ServerDlLimitBypass=\"0\" UlType=\"0\" UlLimit=\"10\" ServerUlLimitBypass=\"0\">
	<Download/>
	<Upload/>
	</SpeedLimits>
	</User>
	</Users>
	</FileZillaServer>";
        $editfile = fopen($filezilla_config, "w");
        if (!fwrite($editfile, $new_file)) {
            return false;
        } else {
            return true;
        }
        fclose($editfile);
    } else {
        # Server is POSIX based - Lets use ProFTPd
        $proftpd_config = "/etc/zpanel/conf/ftp/zftpd.passwd";
        $auto_salt = GenerateRandomPassword(8);
        $ftp_password = crypt($password, '$1$' . $auto_salt . '$');
        $directorytouse = ChangeWinSlashesToNIX($directorytouse);
        $new_file = implode('', file($proftpd_config));
        $new_file .= "# USER:" . $username . "\n" . $username . ":" . $ftp_password . ":1010:1010::" . $directorytouse . ":/bin/false\n# END USER:" . $username . "\n";
        $editfile = fopen($proftpd_config, "w");
        if (!fwrite($editfile, preg_replace('/^[ \t]*[\r\n]+/m', '', $new_file))) {
            # Log to system, unable to write the file...
            TriggerLog(1, "Was unable to write to the ProFTPd configuration file (" . $proftpd_config . "), check that the file is not read-only and that the file path in the ZPanel settings is correct.");
            return false;
        } else {
            return true;
            # Reload the FTP daemon here!!
        }
        fclose($editfile);
    }
}

# Edit a FileZilla FTP Account

function zapi_ftpaccount_edit($filezilla_root, $username, $newpassword) {
    if (ShowServerPlatform() == "Windows") {
    # Reset the account password for the FTP account!
    $filezilla_reload = "\"" . $filezilla_root . "FileZilla server.exe\" /reload-config";
    $fzpath = $filezilla_root . "FileZilla Server.xml";
    $fzfile = file_get_contents($fzpath) or die("Can't Open FileZilla configuration file!");
    $startpos = strpos($fzfile, "<User Name=\"" . $username . "\">");
    $endpos = strpos($fzfile, "</User>");
    $endposlength = "</User>";
    $endposlength = strlen($endposlength);
    $fzrecord = substr($fzfile, $startpos, ($endpos - $startpos) + $endposlength);
    $replacement = "<Option Name=\"Pass\">" . md5($newpassword) . "</Option>";
    $newfzrecord = preg_replace('/<Option Name=\"Pass\">.*?<\/Option>/', $replacement, $fzrecord);
    $fzfile = substr_replace($fzfile, $newfzrecord, $startpos, ($endpos - $startpos) + $endposlength);
    $filehandle = fopen($fzpath, 'w') or die("Can't Open FileZilla configuration file!");
    if (!fwrite($filehandle, $fzfile)) {
        return false;
    } else {
        return true;
    }
    fclose($filehandle);
    } else {
        # Server is POSIX based - Lets use ProFTPd
        $proftpd_config = "/etc/zpanel/conf/ftp/zftpd.passwd";
        $auto_salt = GenerateRandomPassword(8);
        $ftp_password = crypt($newpassword, '$1$' . $auto_salt . '$');
        $filein = file_get_contents($proftpd_config);
        $startpos = strpos($filein, "# USER:" . $username . "");
        $endpos = strpos($filein, "# END USER:" . $username . "");
        $endposlength = "# END USER:" . $username . "";
        $endposlength = strlen($endposlength);
        
        $record = substr($filein, $startpos, ($endpos - $startpos) + $endposlength);
        
        $split = explode(":1010:1010::", $record);
        $split1 = explode($username . ":", $split[0]);
        $split1[1] = $ftp_password;
        
        $newrecord = $split1[0] . " " . $username . ":" . $split1[1] . ":1010:1010::" . $split[1];
        $fileout = substr_replace($filein, $newrecord, $startpos, ($endpos - $startpos) + $endposlength);
        
        $fh = fopen($proftpd_config, 'w') or die(TriggerLog(1, $b = "zpanel_kernel - cant open proftp config"));
        $write = fwrite($fh, $fileout);

            if ($write) {
                TriggerLog(1, $b = "zpanel_kernel - write to proftp config successful");
    } else {
                TriggerLog(1, $b = "zpanel_kernel - write to proftp config FAILED");
}
            fclose($fh);

    }
}

# Remove a FileZilla FTP Account

function zapi_ftpaccount_remove($filezilla_root, $username) {
    if (ShowServerPlatform() == "Windows") {
        # Now we go and delete the FTP account infomation from the FileZilla configuration file...
        $filezilla_reload = "\"" . $filezilla_root . "FileZilla server.exe\" /reload-config";
        $filezilla_config = $filezilla_root . "FileZilla Server.xml";
        $content = implode('', file($filezilla_config));
        $content1 = explode("<User Name=\"$username\">", $content);
        $content2 = explode("</User>", $content1[1], 2);
        $content = $content1[0] . $content2[1];
        $editfile = fopen($filezilla_config, "w");
        if (!fwrite($editfile, $content)) {
            return false;
        } else {
            return true;
        }
        fclose($editfile);
    } else {
        # Now we go and delete the FTP account infomation from the ProFTPd configuration file
        $proftpd_config = "/etc/zpanel/conf/ftp/zftpd.passwd";
        $content = implode('', file($proftpd_config));
        $content1 = explode("# USER:" . $username . "", $content);
        $content2 = explode("# END USER:" . $username . "", $content1[1], 2);
        $content = $content1[0] . $content2[1];
        $editfile = fopen($proftpd_config, "w");
        if (!fwrite($editfile,  preg_replace('/^[ \t]*[\r\n]+/m', '', $content))) {
            return false;
        } else {
            return true;
        }
        fclose($editfile);
    }
}

# MYSQL SECTION!!!
# Add a MySQL database

function zapi_mysqldb_add($username, $databasename, $charset, $collate, $zdb) {
    $sql = "CREATE DATABASE `" . Cleaner('i', $username . "_" . $databasename) . "` DEFAULT CHARACTER SET " . $charset . " COLLATE " . $collate . ";";
    mysql_query($sql, $zdb);
    $sql = "GRANT ALL PRIVILEGES ON `" . $username . "\_" . $databasename . "`.* TO '" . $username . "'@'%'";
    $result = mysql_query($sql, $zdb) or die(TriggerLog(1, "Error whilst granting priviledges to MySQL user, MySQL error was: " . mysql_error()));
    return true;
}

# Remove a MySQL database

function zapi_mysqldb_remove($databasename, $zdb) {
    $sql = "DROP DATABASE IF EXISTS `" . $databasename . "`;";
    mysql_query($sql, $zdb);
    return true;
}

# Add a MySQL user

function zapi_mysqluser_add($username, $zdb) {
    $sql = "CREATE USER `" . $username . "`@`%`;";
    $result = mysql_query($sql, $zdb) or die(TriggerLog(1, "Error whilst creating MySQL user, MySQL error was: " . mysql_error()));
    #The USAGE privilage type allows you to create a user with no privilages. It assums you will grant database-specific privilages later
    $sql = "GRANT USAGE ON * . * TO `" . $username . "`@`%`;";
    $result = mysql_query($sql, $zdb) or die(TriggerLog(1, "Error whilst granting usages for MySQL user, MySQL error was: " . mysql_error()));
}

function zapi_mysqluser_remove($username, $zdb) {
    $sql = "DROP USER `" . $username . "`@`%`;";
    mysql_query($sql, $zdb);
}

# Set a password for a MySQL user

function zapi_mysqluser_setpass($username, $password, $zdb) {
    $sql = "SET PASSWORD FOR `" . $username . "`@`%`=PASSWORD('" . $password . "')";
    $result = mysql_query($sql, $zdb) or die(TriggerLog(1, "Error whilst setting password for MySQL user, MySQL error was: " . mysql_error()));
}

# FILE SYSTEM SECTION!!
# Create a folder

function zapi_filesystem_add($folder) {
    if (!file_exists($folder)) {
        @mkdir($folder, 0777);
        if (ShowServerPlatform() <> "Windows") {
            # Lets set some more permissions on it so it can be accessed correctly! (eg. 0777 permissions)
            @chmod($folder, 0777);
        }
    } else {
        # Folder already exist... Just ignore the request!
    }
    return $resault;
}

# Delete a folder and all subfolders

function zapi_filesystem_remove($folder) {
    if (file_exists($folder)) {
        SureRemoveDir($folder, true);
    }
    return;
}

# REMOTE CONTENT SECTION!!!
# Read the news from the ZPanel website...

function zapi_news_display() {
    $newsurl = "http://api.zpanelcp.com/api/news.php";
    $handle = @file_get_contents($newsurl);
    $content = $handle;
    if ($content == '') {
        $content = "Unable to connect to the ZPanel News server at this time.";
    } else {
        $content = $handle;
    }
    echo $content;
    return;
}

function zapi_version_check($version) {
    $updateurl = "http://api.zpanelcp.com/api/version.php?sv=" . GetSystemOption('zpanel_version') . "";
    $handle = @file_get_contents($updateurl);
    $content = $handle;
    if ($content == '') {
        $content = "Unable to connect to the ZPanel Version Checker Service at this time.";
    } else {
        $content = $handle;
    }
    echo $content;
    return;
}

# SHADOW USER SECTION!!!!
# Shadow a users session...

function zapi_shadow_user($username, $userid, $adminname) {
    # Register the session variables for the user that the admin is shadowing....
    TriggerLog($userid, $b = "User (" . $useraccount['ac_user_vc'] . ") shadowing user (" . $username . ") connecting...");
    $_SESSION['zUsername'] = $username;
    $_SESSION['zUserID'] = $userid;
    header("location: ./");
    exit;
}

# VHOST SECTION!!!!
# Add a domain

function zapi_vhdomain_add($apache_conf, $domain, $alias, $admin_email, $home_dir, $flags, $alogs, $handlers, $errorpages, $extra, $directory_index) {
    $new_file = implode('', file($apache_conf));
    $new_file .= "
# DOMAIN: $domain
<virtualhost *:80>
ServerName $domain
$alias
ServerAdmin " . $admin_email . "
DocumentRoot \"" . $home_dir . "\"
$flags
$alogs
<Directory />
Options FollowSymLinks Indexes
AllowOverride All
Order Allow,Deny
Allow from all
</Directory>
$handlers
$errorpages
$extra
" . $directory_index . "
</virtualhost>
# END DOMAIN: $domain";
    $editfile = fopen($apache_conf, "w");
	if (ShowServerPlatform() != "Windows") {
		$new_file = str_replace("\r", "", $new_file);
	}
    fwrite($editfile, $new_file);
    fclose($editfile);
    return;
}

# Add a parked domain

function zapi_vhparked_add($apache_conf, $domain, $parking_path) {
    $new_file = implode('', file($apache_conf));
    $new_file .= "
# DOMAIN: $domain
<VirtualHost *:80>
ServerName " . $domain . "
ServerAlias " . $domain . " www." . $domain . "
DocumentRoot \"" . str_replace("g/", "g", $parking_path) . "\"
<Directory />
Options FollowSymLinks Indexes
AllowOverride All
Order Allow,Deny
Allow from all
</Directory>
AddType application/x-httpd-php .php
AddType application/x-httpd-php .php3
</VirtualHost>
# END DOMAIN: $domain";
    $editfile = fopen($apache_conf, "w");
	if (ShowServerPlatform() != "Windows") {
		$new_file = str_replace("\r", "", $new_file);
	}
    fwrite($editfile, $new_file);
    fclose($editfile);
    return;
}

# Add a sub domain

function zapi_vhsub_add($apache_conf, $domain, $alias, $admin_email, $home_dir, $flags, $alogs, $handlers, $errorpages, $extra, $directory_index) {
    $new_file = implode('', file($apache_conf));
    $new_file .= "
# DOMAIN: $domain
<virtualhost *:80>
ServerName $domain
$alias
ServerAdmin " . $admin_email . "
DocumentRoot \"" . $home_dir . "\"
$flags
$alogs
<Directory />
Options FollowSymLinks Indexes
AllowOverride All
Order Allow,Deny
Allow from all
</Directory>
$handlers
$errorpages
$extra
" . $directory_index . "
</virtualhost>
# END DOMAIN: $domain";
    $editfile = fopen($apache_conf, "w");
	if (ShowServerPlatform() != "Windows") {
		$new_file = str_replace("\r", "", $new_file);
	}
	fwrite($editfile, $new_file);
    fclose($editfile);
    return;
}

# Remove a VHOST

function zapi_vhost_remove($apache_conf, $domain) {
    # Now we go and delete the domain infomation from the VHOST file.
    $content = implode('', file($apache_conf));
    $content1 = explode("
# DOMAIN: " . $domain . "", $content);
    $content2 = explode("# END DOMAIN: " . $domain . "", $content1[1], 2);
    $content = $content1[0] . $content2[1];
    $editfile = fopen($apache_conf, "w");
    fwrite($editfile, $content);
    fclose($editfile);
    return;
}

?>
