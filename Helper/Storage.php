<?php

namespace Future\ImageSearch\Helper;


use Google\Cloud\Storage\StorageClient;  
use Magento\Framework\App\Helper\AbstractHelper;
use Future\ImageSearch\Helper\Values;  

 

class Storage extends AbstractHelper
{
    protected $bucket;

    protected $projectValue;

        //$bucketName="",$keyFilePath="config/creds.json",$projectId = "my-project-1-259811")
       //$store = new Storage("suckie");
//$store->upload_object(rand(100,1000)."/luckie.jpg","0.jpg");

//$store->download_object("183/luckie.jpg","tee.jpg");


        public function __construct(
            Values $projectValue
        )
        {          
            $this->projectValue = $projectValue;

            $storage = new StorageClient([
                    'keyFilePath' => $this->projectValue->getGoogleApiKey(),
                    'projectId' => $this->projectValue->getProjectId()
                ]);
            $this->bucketName = $this->projectValue->getBucketName();
            $this->bucket = $storage->bucket($this->bucketName);
        }


    

        
        public function uploadObject($objectName, $source)
        {
           
            $file = fopen($source, 'r'); 
            $object = $this->bucket->upload($file, [
                'name' => $objectName
            ]);
            return "gs://$this->bucketName/$objectName";
        }
    
    /**
     * List Cloud Storage bucket objects.
     *
     * @param string $bucketName the name of your Cloud Storage bucket.
     *
     * @return void
     */
    public function listObjects()
    {
     $objects=array();
        foreach ($this->bucket->objects() as $object) {
            //printf('Object: %s' . PHP_EOL, $object->name());
            array_push($objects,$object->name());
        }
        return $objects;
    }
    
    /**
     * List Cloud Storage bucket objects with specified prefix.
     *
     * @param string $bucketName the name of your Cloud Storage bucket.
     *
     * @return void
     */
    public function listObjectsWithPrefix($prefix)
    {
        $objects=array();
        $options = ['prefix' => $prefix];
        foreach ($this->bucket->objects($options) as $object) {
            //printf('Object: %s' . PHP_EOL, $object->name());
            array_push($objects,$object->name());
        }
        return $objects;
    }
    
    /**
     * Download an object from Cloud Storage and save it as a local file.
     *
     * @param string $bucketName the name of your Google Cloud bucket.
     * @param string $objectName the name of your Google Cloud object.
     * @param string $destination the local destination to save the encrypted object.
     *
     * @return void
     */
    public function downloadObject($objectName, $destination)
    {
        $object = $this->bucket->object($objectName);
        $object->downloadToFile($destination);
        printf('Downloaded gs://%s/%s to %s' . PHP_EOL,$this->bucketName, $objectName, basename($destination));
    }
    
    /**
     * Move an object to a new name and/or bucket.
     *
     * @param string $objectName the name of your Cloud Storage object.
     * @param string $newBucketName the destination bucket name.
     * @param string $newObjectName the destination object name.
     *
     * @return void
     */
    public function moveObject($objectName, $newBucketName, $newObjectName)
    {
        $object = $this->bucket->object($objectName);
        $object->copy($newBucketName, ['name' => $newObjectName]);
        $object->delete();
        printf('Moved gs://%s/%s to gs://%s/%s' . PHP_EOL,$this->bucketName,$objectName,$newBucketName,$newObjectName);
    }
    
    /**
     * List object metadata.
     *
     * @param string $bucketName the name of your Cloud Storage bucket.
     * @param string $objectName the name of your Cloud Storage object.
     *
     * @return void
     */
    public function objectMetadata($objectName)
    {
        $object = $this->bucket->object($objectName);
        $info = $object->info();
        printf('Blob: %s' . PHP_EOL, $info['name']);
        printf('Bucket: %s' . PHP_EOL, $info['bucket']);
        printf('Storage class: %s' . PHP_EOL, $info['storageClass']);
        printf('ID: %s' . PHP_EOL, $info['id']);
        printf('Size: %s' . PHP_EOL, $info['size']);
        printf('Updated: %s' . PHP_EOL, $info['updated']);
        printf('Generation: %s' . PHP_EOL, $info['generation']);
        printf('Metageneration: %s' . PHP_EOL, $info['metageneration']);
        printf('Etag: %s' . PHP_EOL, $info['etag']);
        printf('Crc32c: %s' . PHP_EOL, $info['crc32c']);
        printf('MD5 Hash: %s' . PHP_EOL, $info['md5Hash']);
        printf('Content-type: %s' . PHP_EOL, $info['contentType']);
        printf("Temporary hold: " . ($info['temporaryHold'] ? "enabled" : "disabled") . PHP_EOL);
        printf("Event-based hold: " . ($info['eventBasedHold'] ? "enabled" : "disabled") . PHP_EOL);


        if ($info['retentionExpirationTime']) {
            printf("retentionExpirationTime: " . $info['retentionExpirationTime'] . PHP_EOL);
        }
        if (isset($info['metadata'])) {
            printf('Metadata: %s', print_r($info['metadata'], true));
        }
    }
    
    /**
     * Delete an object.
     *
     * @param string $bucketName the name of your Cloud Storage bucket.
     * @param string $objectName the name of your Cloud Storage object.
     * @param array $options
     *
     * @return void
     */
    public function deleteOobject($objectName, $options = [])
    {
        $object = $this->bucket->object($objectName);
        $object->delete();
       // printf('Deleted gs://%s/%s' . PHP_EOL,$this->bucketName, $objectName);          
    }

      /**
         * Set the value of bucketName
         *
         * @return  self
         */ 
    public function setBucketName($bucketName)
    {
        $this->bucketName = $bucketName;
        return $this;
    }

}


