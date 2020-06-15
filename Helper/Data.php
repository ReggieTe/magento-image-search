<?php

namespace Future\ImageSearch\Helper;



use Magento\Downloadable\Helper\File;
use Magento\Framework\Filesystem\DirectoryList ;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;

use Google\Cloud\Vision\V1\ProductSearchClient;
use Google\Cloud\Vision\V1\ProductSet;
use Google\Cloud\Vision\V1\ImportProductSetsGcsSource;
use Google\Cloud\Vision\V1\ImportProductSetsInputConfig;
use Google\Cloud\Vision\V1\Product;
use Google\Cloud\Vision\V1\ReferenceImage;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\ProductSearchParams;
use Google\Cloud\Vision\V1\Product_KeyValue;
use Google\Protobuf\FieldMask;


use Future\ImageSearch\Helper\Values;


class Data extends AbstractHelper
{
    
   
    protected $file; 

    protected $directory; 

    protected $storeManager;

    protected $projectId;

    protected $location;

    protected $client;

    protected $projectValue;

    public function __construct(
        File $file,
        DirectoryList $directory,
        StoreManagerInterface $storeManager,
        Values $projectValue        
    ){
        $this->file = $file;
        $this->directory = $directory;
        $this->storeManager = $storeManager; 
        $this->projectValue = $projectValue;   
        $key=$this->projectValue->getGoogleApiKey();
        putenv("GOOGLE_APPLICATION_CREDENTIALS=$key");
        $this->projectId = $this->projectValue->getProjectId();
        $this->location = $this->projectValue->getProjectLocation();
        $this->client = new ProductSearchClient ();

    }
  
        /**
         * Search similar products to image
         *
         * @param string $productSetId ID of the product set
         * @param string $productCategory Category of the product
         * @param string $gcs Google Cloud Storage path of the image to be searched
         * @param string $filter Condition to be applied on the labels
         */
        public function productSearchSimilarRemoteUsingResources($productSetId, $productCategory, $gcsUri, $filter)
        {
            $availableProducts=array();
            $imageAnnotatorClient = new ImageAnnotatorClient();
            $productSearchClient = new ProductSearchClient();
    
            # get the name of the product set
            $productSetPath = $productSearchClient->productSetName($this->projectId,$this->location,$productSetId);
    
            # product search specific parameters
            $productSearchParams = (new ProductSearchParams())
                ->setProductSet($productSetPath)
                ->setProductCategories([$productCategory])
                ->setFilter($filter);
    
            # search products similar to the image
            $response = $imageAnnotatorClient->productSearch($gcsUri, $productSearchParams);
    
            if ($productSearchResults = $response->getProductSearchResults()) {
                $indexTime = $productSearchResults->getIndexTime();
                // $indexTime->getSeconds(), $indexTime->getNanos());
    
                $results = $productSearchResults->getResults();
                foreach ($results as $result) {
    
                    # display the product information.
                    $product = $result->getProduct();
                    $productName = $product->getName();
                    $productNameArray = explode('/', $productName);
    
                    $availableProduct=array(
                        'name'=>$productName,
                        'id'=> end($productNameArray),
                        'display_name'=>$product->getDisplayName(),
                        'description'=>$product->getDescription(),
                        'category'=>$product->getProductCategory(),
                        'labels' => $product->getProductLabels(),
                        'confidence_score'=>$result->getScore()
                    );
                    array_push($availableProducts,$availableProduct);
                }
            } else {
                array_push($availableProducts ,$response->getError()->getMessage());
            }
    
            $imageAnnotatorClient->close();
            $productSearchClient->close();

            return $availableProducts;
        }
        /**
         * Search similar products to image
         *
         * @param string $productSetId ID of the product set
         * @param string $productCategory Category of the product
         * @param string $filePath Local file path of the image to be searched
         * @param string $filter Condition to be applied on the labels
         */
        public function productSearchSimilar($productSetId, $productCategory, $filePath, $filter)
        {
            $availableProducts=array();
            $imageAnnotatorClient = new ImageAnnotatorClient();
            $productSearchClient = new ProductSearchClient();
    
            # read the image
            $image = file_get_contents($filePath);
    
            # get the name of the product set
            $productSetPath = $productSearchClient->productSetName($this->projectId,$this->location,$productSetId);
    
            # product search specific parameters
            $productSearchParams = (new ProductSearchParams())
                ->setProductSet($productSetPath)
                ->setProductCategories([$productCategory])
                ->setFilter($filter);
    
            # search products similar to the image
            $response = $imageAnnotatorClient->productSearch($image, $productSearchParams);
    
            if ($productSearchResults = $response->getProductSearchResults()) {
                $indexTime = $productSearchResults->getIndexTime();
               // 'set index time: %d seconds %d nanos' . PHP_EOL, $indexTime->getSeconds(), $indexTime->getNanos());
    
                $results = $productSearchResults->getResults();
               // print('Search results: ' . PHP_EOL);
                foreach ($results as $result) {
                    //printf('Score (confidence): %d' . PHP_EOL, $result->getScore());    
                    # display the product information.
                    $product = $result->getProduct();
                    $productName = $product->getName();
                    $productNameArray = explode('/', $productName);    
                    
                    $availableProduct=array(
                        'name'=>$productName,
                        'id'=> end($productNameArray),
                        'display_name'=>$product->getDisplayName(),
                        'description'=>$product->getDescription(),
                        'category'=>$product->getProductCategory(),
                        'labels' => $product->getProductLabels(),
                        'confidence_score'=>$result->getScore()
                    );
                    array_push($availableProducts,$availableProduct);

                }
            } else {
                return $response->getError();
            }
    
            $imageAnnotatorClient->close();
            $productSearchClient->close();

            return $availableProducts;
        }
        /**
         * Delete all products not in any product sets.
         *
         * @param boolean $force force purge
         */
        public function purgeOrphanProducts($force)
        {
            $parent = $this->client->locationName($this->projectId, $this->location);
            $operationResponse  = $this->client->purgeProducts($parent, ['deleteOrphanProducts' => true, 'force' => $force]);
            $operationResponse->pollUntilComplete();
            if ($operationResponse->operationSucceeded()) {
                return true;
            # print_r($operationResponse->getResult());
            } else {
                
               // print_r($operationResponse->getError());
                return false;
            }
            $this->client->close();
        }
        /**
         * Delete all products in a product set.
         *
         * @param string $product_set_id ID of the product
         * @param boolean $force force purge
         */
        public function purgeProductsInProductSet($product_set_id, $force)
        {            
    
            $parent = $this->client->locationName($this->projectId, $this->location);
            $product_set_purge_config = (new ProductSetPurgeConfig())->setProductSetId($product_set_id);
            $operationResponse  = $this->client->purgeProducts($parent, ['productSetPurgeConfig' => $product_set_purge_config,'force' => $force]);
            $operationResponse->pollUntilComplete();
            if ($operationResponse->operationSucceeded()) {
                return true;
            # print_r($operationResponse->getResult());
            } else {
                
                print_r($operationResponse->getError());
                return false;
            }
            $this->client->close();
        }
        /**
         * Deletes product set
         *
         * @param string $productSetId ID of the product
         */
        public function productSetDelete($productSetId)
        {
            # get the name of the product set
            $productSetPath = $this->client->productSetName($productSetId);    
            # delete the product set
            $this->client->deleteProductSet($productSetPath);         
    
            $this->client->close();
        }
        /**
         * Delete the product and all its reference images.
         *
         
    
        * @param string $productId ID of the product
        */
        public function productDelete($productId)
        {
            
    
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # delete the product
            $this->client->deleteProduct($productPath);
    
            $this->client->close();
        }
        /**
         * Delete a reference image
         *
         
    
        * @param string $productId ID of the product
        * @param string $referenceImageId ID of the reference image
        */
        public function productImageDelete($productId, $referenceImageId)
        {   
            # get the name of the reference image.
            $referenceImagePath = $this->client->referenceImageName($productId, $referenceImageId);
    
            # delete the reference image
            $this->client->deleteReferenceImage($referenceImagePath);
    
            $this->client->close();
        }
        /**
         * Update product labels
         *
         
    
        * @param string $productId ID of the product
        * @param string $key Key of the label to update
        * @param string $value Value of label to update
        */
        public function productUpdate($productId, $key, $value)
        {
               
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # set product name, product label and product display name.
            # multiple labels are also supported.
            $keyValue = new Product_KeyValue();
            $keyValue->setKey($key)->setValue($value);

            $product = new Product();
            $product ->setName($productPath)->setProductLabels([$keyValue]);
    
            # updating only the product labels field here.
            $updateMask = (new FieldMask())
                ->setPaths(['product_labels']);
    
            # this overwrites the product_labels.    
           $updatedProduct = $this->client->updateProduct($product, ['updateMask' => $updateMask]);
            # display the product information.
            array('name'=>$updatedProduct->getName(),
            'labels: '=> $product->getProductLabels());

            $this->client->close();
        }
        /**
         * Get info about a reference image
         *
         
    
        * @param string $productId ID of the product
        * @param string $referenceImageId ID of the reference image
        */
        public function productImageGet($productId, $referenceImageId)
        {
            
    
            # get the name of the reference image.
            $referenceImagePath = $this->client->referenceImageName($productId, $referenceImageId);
    
            # get complete detail of the reference image.
            $referenceImage = $this->client->getReferenceImage($referenceImagePath);
    
            # display reference image information
            $name = $referenceImage->getName();
            $nameArray = explode('/', $name);
    
            array('Reference image name'=>$name);
            array('Reference image id'=>end($nameArray));
            array('Reference image uri'=>$referenceImage->getUri());
            array('Reference image bounding polygons: ');
            foreach ($referenceImage->getBoundingPolys() as $boundingPoly) {
                foreach ($boundingPoly->getVertices() as $vertex) {
                    printf('(%d, %d) ', $vertex->getX(), $vertex->getY());
                }
                print(PHP_EOL);
            }
    
            $this->client->close();
        }
        /**
         * List all images in
         *
         * @param string $productId ID of the product
         */
        public function productImageList($productId)
        {
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # list all the reference images available
            $referenceImages = $this->client->listReferenceImages($productPath);
    
            foreach ($referenceImages->iterateAllElements() as $referenceImage) {
                $name = $referenceImage->getName();
                $nameArray = explode('/', $name);
    
                array('Reference image name'=>$name);
                array('Reference image id'=>end($nameArray));
                array('Reference image uri'=>$referenceImage->getUri());
                array('Reference image bounding polygons: ');

                foreach ($referenceImage->getBoundingPolys() as $boundingPoly) {
                    foreach ($boundingPoly->getVertices() as $vertex) {
                        //printf('(%d, %d) ', $vertex->getX(), $vertex->getY());
                    }
                    
                }
            }
    
            $this->client->close();
        }
        /**
         * Get information about a product
         *
         * @param string $productId ID of the product
         */
        public function productGet($productId)
        {
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # get complete detail of the product.
            $product = $this->client->getProduct($productPath);
    
            # display the product information.
            $productName = $product->getName();
            $productNameArray = explode('/', $productName);
    
            return $availableProduct=array(
                'name'=>$productName,
                'id'=> end($productNameArray),
                'display_name'=>$product->getDisplayName(),
                'description'=>$product->getDescription(),
                'category'=>$product->getProductCategory(),
                'labels' => $product->getProductLabels()
            );

            $this->client->close();
        }
        /**
         * List all products
         *
        */
        public function productList()
        { 
            $availableProducts=array();
            # a resource that represents Google Cloud Platform location.
            $this->locationPath = $this->client->locationName($this->projectId, $this->location);
    
            # list all the products available in the region.
            $products =$this->client->listProducts($this->locationPath);
    
            # display the product information.
            foreach ($products->iterateAllElements() as $product) {
                $name = $product->getName();
                $productNameArray = explode('/', $name);    
               
                $availableProduct=array(
                    'name'=>$name,
                    'id'=> end($productNameArray),
                    'display_name'=>$product->getDisplayName(),
                    'description'=>$product->getDescription(),
                    'category'=>$product->getProductCategory(),
                    'labels' => $product->getProductLabels()
                );
                array_push($availableProducts,$availableProduct);
            }
            $this->client->close();
            
            return $availableProducts;
        }
        /**
         * Get information about a product set
         *
         * @param string $productSetId ID of the product
         */
        public function productSetGet($productSetId)
        {
            # get the name of the product set

            $productSetPath = $this->client->productSetName($this->projectId,$this->location,$productSetId);
    
            # get complete detail of the product set
            $productSet = $this->client->getProductSet($productSetPath);
    
            # display the product set information.
            $name = $productSet->getName();
            $nameArray = explode('/', $name);
            $indexTime = $productSet->getIndexTime();

            return array('set name'=>$name,
            'set id'=>end($nameArray),
            'set display name'=>$productSet->getDisplayName(),
            'set index time seconds' => $indexTime->getSeconds(),
            'set index time nanos' =>  $indexTime->getNanos());
            
            $this->client->close();
        }
        /**
         * List all product set
         *
         */
        public function productSetList()
        { 
            $productSetList=array();
            # a resource that represents Google Cloud Platform location.
            $this->locationPath = $this->client->locationName($this->projectId, $this->location);
    
            # a resource that represents Google Cloud Platform location.
            $productSets = $this->client->listProductSets($this->locationPath);
    
            # display the product set information.
            foreach ($productSets->iterateAllElements() as $productSet) {
                $name = $productSet->getName();
                $nameArray = explode('/', $name);
                $indexTime = $productSet->getIndexTime();
    
                array_push($productSetList ,array('name'=>$name,
                'id'=>end($nameArray),
                'name'=>$productSet->getDisplayName(),
                'seconds' => $indexTime->getSeconds(),
                'nanos' =>  $indexTime->getNanos()));
                
            }
    
            $this->client->close();
            return $productSetList;
        }
        /**
         * Create a reference image
         *
         * @param string $productId ID of the product
         * @param string $referenceImageId ID of the reference image
         * @param string $gcsUri Google Cloud Storage path of the input image
         */
        public function productImageCreate($productId, $referenceImageId, $gcsUri)
        {
            
    
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # create a reference image.
            $referenceImage = (new ReferenceImage())
                ->setUri($gcsUri);
    
            # the response is the reference image with `name` populated.
            $image = $this->client->createReferenceImage($productPath, $referenceImage, ['referenceImageId' => $referenceImageId]);
    
            # display the reference image information
            return array('Reference image name'=>$image->getName(),'Reference image uri'=>$image->getUri());
    
            $this->client->close();
        }
        /**
         * Create a product set
         *
         
    
        * @param string $productId ID of the product
        * @param string $productSetId ID of the product set
        */
        public function productSetRemoveProduct($productId, $productSetId)
        {
            
    
            # get the name of the product set
            $productSetPath = $this->client->productSetName($productSetId);
    
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # add product to product set
            $this->client->removeProductFromProductSet($productSetPath, $productPath);
    
            $this->client->close();
        }
        /**
         * Create a product set
         *
         
    
        * @param string $productId ID of the product
        * @param string $productSetId ID of the product set
        */
        public function productSetAddProduct($productId, $productSetId)
        {
            
    
            # get the name of the product set
            $productSetPath = $this->client->productSetName($this->projectId,$this->location,$productSetId);
    
            # get the name of the product.
            $productPath = $this->client->productName($this->projectId,$this->location,$productId);
    
            # add product to product set
            $this->client->addProductToProductSet($productSetPath, $productPath);
    
            $this->client->close();
        }
        /**
         * Create one product
         *
         * @param string $productId ID of the product
         * @param string $productDisplayName Display name of the product
         * @param string $productCategory Category of the product
         */
        public function productCreate($productId, $productDisplayName, $productCategory)
        {
            # a resource that represents Google Cloud Platform location.
            $this->locationPath = $this->client->locationName($this->projectId, $this->location);
    
            # create a product with the product specification in the region.
            # set product name and product display name.
            $product = (new Product())
                ->setDisplayName($productDisplayName)
                ->setProductCategory($productCategory);
    
            # the response is the product with the `name` field populated.
            $response = $this->client->createProduct($this->locationPath, $product, ['productId' => $productId]);
    
            # display the product information.
            return array('name'=>$response->getName());
    
            $this->client->close();
        }
        /**
         * Import images of different products in the product set.
         *
         * @param string $gcsUri Google Cloud Storage URI
         */
        public function productSetImport($gcsUri)
        {    
    
            # a resource that represents Google Cloud Platform location.
            $this->locationPath = $this->client->locationName($this->projectId, $this->location);
    
            # set the input configuration along with Google Cloud Storage URI
            $gcsSource = (new ImportProductSetsGcsSource())
                ->setCsvFileUri($gcsUri);
            $inputConfig = (new ImportProductSetsInputConfig())
                ->setGcsSource($gcsSource);
    
            # import the product sets from the input URI
            $operation = $this->client->importProductSets($this->locationPath, $inputConfig);
            $operationName = $operation->getName();
           // printf('Processing operation name'=>$operationName);
    
            $operation->pollUntilComplete();
            print('Processing done.' . PHP_EOL);
    
            if ($result = $operation->getResult()) {
                $referenceImages = $result->getReferenceImages();
    
                foreach ($result->getStatuses() as $count => $status) {
                    printf('Status of processing line %d of the csv: ' . PHP_EOL, $count);
                    # check the status of reference image
                    # `0` is the code for OK in google.rpc.Code.
                    if ($status->getCode() == 0) {
                        $referenceImage = $referenceImages[$count];
                        //printf('name'=>$referenceImage->getName());
                        //printf('uri'=>$referenceImage->getUri());
                    } else {
                        //printf('Status code not OK'=>$status->getMessage());
                    }
                }
                print('IMPORTANT: You will need to wait up to 30 minutes for indexing to complete' . PHP_EOL);
            } else {
                //printf('Error'=>$operation->getError()->getMessage());
            }
    
            $this->client->close();
        }
        /**
         * Create a product set
         * 
         * @param string $productSetId ID of the product set
         * @param string $productSetDisplayName Display name of the product set
         */
        public function productSetCreate($productSetId, $productSetDisplayName)
        {
            
    
            # a resource that represents Google Cloud Platform location.
            $this->locationPath = $this->client->locationName($this->projectId, $this->location);
    
            # create a product set with the product set specification in the region.
            $productSet = (new ProductSet())
                ->setDisplayName($productSetDisplayName);
    
            # the response is the product set with the `name` field populated.
            $response = $this->client->createProductSet($this->locationPath, $productSet, ['productSetId' => $productSetId]);
    
            # display the product information.
            return array('set name'=> $response->getName());
    
            $this->client->close();
        }

    public function checkIfExist($items=array(),$lookingFor='')
        {
            $setExist=false;
            foreach($items as $item)
            {
                if($item['id'] == $lookingFor)
                {
                    $setExist=true;
                }
            }
            return $setExist;
        }
    
    }

