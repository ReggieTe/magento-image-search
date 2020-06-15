<?php 
namespace Future\ImageSearch\Model\Config\Source;

class AllowedCategories implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'homegoods-v2', 'label' => __('Home Goods')],
            ['value' => 'apparel-v2', 'label' => __('Apparel')],
            ['value' => 'toys-v2', 'label' => __('Toys')],
            ['value' => 'packagedgoods-v1', 'label' => __('Package Goods')],
            ['value' => 'general-v1', 'label' => __('General')]
        ];
    }
}
 
