<?php
namespace Future\ImageSearch\Model\Config\Backend;

class File extends \Magento\Config\Model\Config\Backend\File
{
    /**
     * @return string[]
     */
    public function getAllowedExtensions() {
        return ['json'];
    }
}