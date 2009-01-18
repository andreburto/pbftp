<?php

/*****
 * PBFTP is a PHP class designed to use FTP functions as an adhoc API for
 * premium Photobucket accounts.  It can upload files, create directories,
 * delete directories, navigate directories, display images in a directory,
 * and display a links of WWW links to images in an album.  It cannot delete
 * images from an album, as that is not allowed via FTP.
 *
 * Created by: Andrew Burton, tuglyraisin@aol.com, http://andrewburton.biz
 *
 * This code is available without license or waranty for public domain
 * consumption.  Enjoy!
 *****/

/* PBFTP - version 0.1-ish */
class PBFTP
{
    /* These are public so they can be accessed and changed from
     * a higher scope. */
    public $ftp_user;
    public $ftp_pass;
    public $album_url;
    
    /* Private, there's no need to access this outside the class. */
    private $conn_id;
    
    /* Sets up the values of the new object, initates an FTP connection,
     * but does not login.
     * @param string $user is the Photobucket username
     * @param string $pass is the Photobucket password
     * @param string $album is the WWW address to the user's photo album */
    function __construct($user = null, $pass = null, $album = null)
    {
        define("PB_FTP", "ftp.photobucket.com");
        define("PB_URL", "http://photobucket.com");
        define("FTP_PORT", 21);
        define("FTP_TIMEOUT", 30);
        
        $this->ftp_user = isset($user) ? $user : null;
        $this->ftp_pass = isset($pass) ? $pass : null;
        $this->album_url = isset($album) ? $album : null;
        
        $this->conn_id = @ftp_connect(PB_FTP, FTP_PORT, FTP_TIMEOUT);
    }
    
    /* This ensures the FTP connection is closed, either on an error or
     * at the end of the program */
    function __destruct()
    {
        if ($this->conn_id != null)
        {
            @ftp_close($this->conn_id);
        }
    }
    
    /* Displays an error and closes the FTP connection */
    function _error($msg = null)
    {
        if ($msg != null)
        {
            $this->close();
            echo("ERROR: " . $msg);
            exit;
        }
        else
        {
            $this->_error("No message passed.");
        }
    }
    
    /* Cleans off excess slashes from directory paths */
    function _cleanPath($dir = null)
    {
        // Don't bother for ..
        if ($dir == "..")
        {
            return $dir;
        }
        
        // Don't bother if it's just a root slash
        if ($dir == "/")
        {
            return $dir;
        }
        
        // Takes if the intitial "." of $dir, but not ".."
        if (substr($dir, 0, 1) == "." && substr($dir, 0, 2) != "..")
        {
            $dir = substr($dir, 1);
        }
        
        // Takes off the final "/" of the album url
        if (substr($dir, strlen($dir) - 1, 1) == "/")
        {
            $dir = substr($dir, 0, strlen($dir) - 1);
        }
        
        // Takes if the intitial "/" of $dir
        if (substr($dir, 0, 1) == "/")
        {
            $dir = substr($dir, 1);
        }
        
        return $dir;
    }
    
    /* Logs people into the FTP server. */ 
    function login()
    {
        $res = @ftp_login($this->conn_id, $this->ftp_user, $this->ftp_pass);
        
        if ($res == true)
        {
            return true;
        }
        else
        {
            $this->_error("Could not login.");
        }
    }
    
    /* Disconnects from the FTP server. */
    function close()
    {
        $this->__destruct();
        $this->conn_id = null;
        return true;
    }
    
    /* Displays the contents of a directory, directories first then files.
     * @param string $dir is the directory to be displayed
     * @param boolean $thumbnails is a boolean switch, which decides whether or
     * not to show files that begin with "th_", the thumbnail prefix */
    function dir($dir = '.', $thumbnails = false)
    {
        // These arrays will be returned
        $dirs = array();
        $files = array();
        
        // Counter for file lists
        $lcount = 0;
        
        // Cleans up the path
        $dir = $this->_cleanPath($dir);
        
        // Plain list of the directory contents
        $list = @ftp_nlist($this->conn_id, $dir);
        
        // The raw list, which is what we use to find directories
        $rlist = @ftp_rawlist($this->conn_id, $dir);
        
        // Creates a list of directories and files
        while ($lcount < count($rlist))
        {
            // Finds directories
            if (substr($rlist[$lcount], 0, 1) == "d")
            {
                // Display directories
                $dirs[] = $list[$lcount];
            }
            
            // Finds files
            else
            {
                // No thumbnails, sorry
                if ($thumbnails == false)
                {
                    if (substr($list[$lcount], 0, 3) != "th_")
                    {
                        $files[] = $list[$lcount];
                    }
                }
                else
                {
                    $files[] = $list[$lcount];
                }
            }
            
            $lcount++;
        }
        
        return array('dirs' => $dirs, 'files' => $files);
    }

    /* Changes the directory
     * @param string $dir the directory path you want to switch to */
    function chdir($dir = null)
    {
        if ($dir == null)
        {
            $this->_error("No directory passed");
        }
        
        $dir = $this->_cleanPath($dir);
        
        $dirs = array();
        
        if (preg_match("/\//", $dir))
        {
            $dirs = split("/", $dir);
        }
        else
        {
            $dirs[0] = $dir;
        }
        
        foreach ($dirs as $path)
        {
            @ftp_chdir($this->conn_id, $path);
        }
        
        return true;
    }

    /* Creates a new directory.
     * @param string $name is the name of the new direcory
     * @param string $dir the path where you want the directory to be */
    function mkdir($name = null, $dir = ".")
    {
        if ($name == null)
        {
            $this->_error("No new directory passed.");
        }
        
        if ($dir != ".")
        {
            $this->chdir($dir);
        }
        
        $res = @ftp_mkdir($this->conn_id, $name);
        
        if ($res == true)
        {
            return true;
        }
        else
        {
            $this->_error("Could not create directory.");
        }
    }
    
    /* Removes an existing directory.  Will only delete empty directories.
     * @param string $name is the name of the directory to remove */
    function rmdir($name = null)
    {
        if ($name == null)
        {
            $this->_error("No directory name to remove.");
        }
        
        $list = $this->dir($name);
        
        if (count($list['dirs']) > 0 || count($list['files']) > 0)
        {
            $this->_error("The directory " . $name . " is not empty.");
        }
        
        $res = @ftp_rmdir($this->conn_id, $name);
        
        if ($res == true)
        {
            return true;
        }
        else
        {
            $this->_error("Could not remove directory");
        }
    }
    
    /* Uploads an image from the local drive to Photobucket.
     * @param string $local is the local file on your computer
     * @param string $name is the name you want to call the uploaded file
     * @param string $dir the directory where you want to put the new image */
    function uploadImage($local = null, $name = null, $dir = ".")
    {
        if ($local == null)
        {
            $this->_error("No local file passed.");
        }
        
        // If no name is passed, get it from the local file
        if ($name == null)
        {
            $temp = $local;
            
            // In case there are double-backshlases
            while (preg_match('/\\\\/', $temp))
            {
                $temp = preg_replace('/\\\\/', "\\", $temp);
            }
            
            // In case there are any backslashes
            while (preg_match("/\\\/", $temp))
            {
                $temp = preg_replace('/\\\/', "/", $temp);
            }
            
            $temp_array = preg_split("/\//", $temp);
            
            $name = $temp_array[count($temp_array) - 1];
        }
        
        if ($dir != ".")
        {
            $this->chdir($dir);
        }
        
        $res = @ftp_put($this->conn_id, $name, $local, FTP_BINARY);
        
        if ($res == false)
        {
            $this->_error($name . " was not uploaded.");
        }
        
        return true;
    }
    
    /* Lists the images from an album.
     * @param string $dir is the path of the album, such as: "/", "album", or
     * "album/subalbum". */
    function imagesInAlbum($dir = null)
    {
        if ($dir == null)
        {
            $this->_error("No album path passed.");
        }
        
        $dir = $this->_cleanPath($dir);
        
        $list = $this->dir($dir);
        
        $album_url = $this->album_url;
        
        $return_array = array();
        
        // Takes off the final "/" of the album url
        if (substr($album_url, strlen($album_url) - 1, 1) == "/")
        {
            $album_url = substr($album_url, 0, strlen($album_url) - 1);
        }
        
        if (strlen($dir) > 0)
        {
            $album_url .= '/' . $dir;
        }
        
        foreach ($list['files'] as $file)
        {
            $return_array[] = array('file' => $file,
                                    'url' => $album_url.'/'.$file,
                                    'thumb' => $album_url.'/th_'.$file,
                                    'album' => $album_url);
        }
        
        return $return_array;
    }
}

?>