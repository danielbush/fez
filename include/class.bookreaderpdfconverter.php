<?php

include_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."config.inc.php");
include_once(APP_INC_PATH . "class.filecache.php");
include_once(APP_INC_PATH . "class.misc.php");

class bookReaderPDFConverter
{
    private $bookreaderDataPath;
    private $sourceFilePath;
    private $sourceFileStat = array();
    private $log;
    private $queue = array();

    public function __construct()
    {
        $this->log = FezLog::get();
    }

    /**
     * Set input resource (PDF) parameters.
     * @param  $pid
     * @param  $sourceFile
     * @return void
     */
    public function setSource($pid, $sourceFile)
    {

      $log = FezLog::get();
      $log->debug('in setSource with');

        $sourceFile = trim($sourceFile);
//        $this->pid = $pid;
        if(strstr($pid,':'))
        {
            $pid = str_replace(':','_',$pid);
        }
        $log->debug('in setSource with pid '.$pid);
        //Is the source file on the filesystem or do we need to download it?
        if(strstr($sourceFile, 'http://') || strstr($sourceFile, 'https://'))
        {
            $this->sourceFilePath = $this->getURLSource($sourceFile);
        }
        else
        {
            $this->sourceFilePath = $sourceFile;
        }
        $log->debug('in setSource with sourcefile '.$sourceFile);
        $this->sourceInfo();
        $this->bookreaderDataPath = BR_IMG_DIR . $pid . '/' . $this->sourceFileStat['filename'];
        $log->debug('in setSource with BRP '.$this->bookreaderDataPath);
    }

    /**
     * Set queue of pdfs to process. The queue array elements are arrays
     * in the form array($pid,$sourcePath,$conversionMethod).
     * @param array $queue
     * @return void
     */
    public function setQueue(array $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Create a queue based on PID. Includes all pdf resources for that PID.
     * @param  $pid
     * @param string $convMeth
     * @return void
     */
    public function setPIDQueue($pid, $convMeth='pdfToJpg')
    {
        $datastreams = Fedora_API::callGetDatastreams($pid);
        $srcURL = APP_FEDORA_GET_URL."/".$pid . '/';
        $q = array();

        foreach($datastreams as $ds)
        {
            if($ds['MIMEType'] == 'application/pdf')
            {
                $q[] = array($pid, $srcURL .$ds['ID'], $convMeth);
            }
        }
        $this->queue = $q;
    }

    /**
     * Check if resource images have been generated by
     * performing a page count.
     * @param  $resourcePath
     * @return bool
     */
    public function resourceGenerated($resourcePath)
    {
        if(is_dir($resourcePath))
        {
            $pageCount = count(array_filter(scandir($resourcePath),
                          array($this, 'ct')));
        }
        else
        {
            $pageCount = 0;
        }

        return ($pageCount > 0) ? true : false;
    }

    public function ct($element)
    {
        return !in_array($element, array('.','..'));
    }

    /**
     * Gather and store information about the source PDF.
     * @return void
     */
    protected function sourceInfo()
    {
        $parts = pathinfo($this->sourceFilePath);
        $this->sourceFileStat = $parts;
    }

    /**
     * Download a pdf from a URL in chunks and save to a tmp location.
     * @param  $url
     * @return string
     */
    protected function getURLSource($url)
    {
      $log = FezLog::get();
      $log->debug($url);
      $newfile = substr($url, strrpos($url, "/")+1);
      $tmpPth = APP_TEMP_DIR.$newfile;
      Misc::processURL($url, true, APP_TEMP_DIR.$newfile);
//        $parts = pathinfo($url);
//        $fhurl = fopen($url, 'rb');
//        $tmpPth = APP_TEMP_DIR . $parts['basename'];
//        $fhfs = fopen($tmpPth, 'ab');
//        $log->err("in getURLSource writing to ".$tmpPth);
//        while(!feof($fhurl))
//        {
//            fwrite($fhfs, fread($fhurl, 64));
//        }
//        $log->err("in getURLSource wrote to ".$tmpPth);
//        fclose($fhurl);
//        fclose($fhfs);
        $log->debug('returning '.$tmpPth);
        return $tmpPth;
    }

    /**
     * Create a directory for this PDF's images if required
     * @return bool|int
     */
    protected function makePath()
    {
        $dir = 0;
      
        if(!is_dir(APP_PATH.$this->bookreaderDataPath))
        {
            $dir = mkdir(APP_PATH.$this->bookreaderDataPath, 0755, true);
        }
        return $dir;
    }

    /**
     * Run the selected conversion method.
     * @param  $conversionType
     * @param bool $forceRegenerate
     * @return void
     */
    public function run($conversionType, $forceRegenerate=false)
    {

      $log = FezLog::get();
      $log->debug('in run with '.$conversionType);
        if(method_exists($this, $conversionType))
        {
            //Generate the resource images if they're not already there or if we are forcing it to do so.
            $resourceGenerated = ($forceRegenerate) ? false :
                    $this->resourceGenerated($this->bookreaderDataPath);
            if(!$resourceGenerated)
            {
                $this->$conversionType();
            } else {
              $log->err("resource generated already so not doing it");
//              echo "resource generated already so not doing it";
            }

            //Delete the tmp source file if there is one.
            if(strstr($this->sourceFilePath, APP_TEMP_DIR))
            {
                unlink($this->sourceFilePath);
            }
        }
        else
        {
            $log->err('Conversion method does not exist:' . __FILE__ . ':' . __LINE__);
        }
    }

    /**
     * Run the bookreader job queue.
     * @param bool $forceRegenerate
     * @return void
     */
    public function runQueue($forceRegenerate=false)
    {
//      print_r($this->queue);
      $log = FezLog::get();
      $log->debug("in runqueue with ".print_r($this->queue, true));

        foreach($this->queue as $job) {
          $log->debug("in runqueue foreach with ".print_r($job, true));
            if(is_array($job) && count($job) == 3) {
                $log->debug("in runqueue if job ");
                $this->setSource($job[0],$job[1]);
                $this->run($job[2], $forceRegenerate);
                if (APP_FILECACHE == "ON") {
                  $cache = new fileCache($job[0], 'pid='.$job[0]);
                  $cache->poisonCache();
                }
            } else {
                $log->err('Malformed job in bookreader queue:' . __FILE__ . ':' . __LINE__);
            }
        }
    }

    /**
     * Perform a conversion of the PDF to png format. One image per page.
     * @return void
     *
     */
    protected function pdfToPng()
    {
      $log = FezLog::get();
      $log->debug("in pdftopng with ".$this->bookreaderDataPath);


        $this->makePath();
        if(is_writable($this->bookreaderDataPath))
        {
            $cmd = GHOSTSCRIPT_PTH . ' -q -dBATCH -dNOPAUSE -sDEVICE=png16m -r150 -sOutputFile=' .
                   $this->bookreaderDataPath . '/' . $this->sourceFileStat['filename'] . '-%04d.png ' .
                   realpath($this->sourceFilePath);
            shell_exec(escapeshellcmd($cmd));
        }
        else
        {
            $this->log->err('Unable to write page images to directory:' . __FILE__ . ':' . __LINE__);
        }
    }

    /**
     * Perform a conversion of the PDF to jpg format. One image per page.
     * @return void
     *
     */
    protected function pdfToJpg()
    {

      $log = FezLog::get();
      $log->debug("in pdftojpg with ".$this->bookreaderDataPath." and sourcefilepath of ".$this->sourceFilePath);
        $this->makePath();
        if(is_writable(APP_PATH.$this->bookreaderDataPath)) {

          $log->debug("in pdftojpg with writable true");
            // First delete anything in the directory 
          $dh = @opendir(APP_PATH.$this->bookreaderDataPath);
          while (false !== ($obj = readdir($dh)))
              {
                  if($obj == '.' || $obj == '..')
                  {
                      continue;
                  }elseif(preg_match('/^.+\.jpg$/i', $obj))
                  {
                    $log->debug("in pdftojpg with deleting".$obj);
                      unlink(APP_PATH.$this->bookreaderDataPath.'/'.$obj);
                  }
              }
          closedir($dh);
          
            $cmd = GHOSTSCRIPT_PTH . ' -dBATCH -dNOPAUSE -dJPEGQ=80 -sDEVICE=jpeg -r150 -sOutputFile=' .
                   APP_PATH.$this->bookreaderDataPath . '/' . $this->sourceFileStat['filename'] . '-%04d.jpg ' .
                   realpath($this->sourceFilePath);
            $log->debug("in pdftojpg running this command ".$cmd);

            $output = shell_exec(escapeshellcmd($cmd));
            $log->debug("output = ".$output);
        }
        else
        {
            $log->err('Unable to write page images to directory:' . __FILE__ . ':' . __LINE__);
        }
    }
}