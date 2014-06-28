<?php

namespace Frontend\Modules\Slideshow\Widgets;


/**
 * This is the configuration-object
 *
 * @package     frontend
 * @subpackage  slideshow
 *
 * @author      Koen Vinken <koen@tagz.be>
 * @since       1.0
 */

use Frontend\Core\Engine\Base\Widget as FrontendBaseWidget;
use Frontend\Core\Engine\Language as FL;
use Frontend\Core\Engine\Model as FrontendModel;
use Frontend\Core\Engine\Navigation as FrontendNavigation;
use Frontend\Modules\Slideshow\Engine\Model as FrontendSlideshowModel;

class Slideshow extends FrontendBaseWidget
{
    /**
     * Execute the extra
     *
     * @return  void
     */
    public function execute()
    {
        // call the parent
        parent::execute();

        // load template
        $this->loadTemplate();

        // load the data
        $this->getData();

        // parse
        $this->parse();
    }
    
    /**
     * Load the data, don't forget to validate the incoming data
     *
     * @return void
     */
    private function getData()
    {   
        // get image data
        $this->slides = FrontendSlideshowModel::getImages($this->data['gallery_id']);
        

            // only if it contains images
            $this->gallery = FrontendSlideshowModel::getGallery($this->data['gallery_id']); 
    }

    /**
     * Parse
     *
     * @return  void
     */
    private function parse()
    {
        // add CSS
        $this->header->addCSS('/src/frontend/modules/' . $this->getModule() . '/layout/css/slideshow.css');

        // assign
        $this->tpl->assign('widgetSlideshow', $this->slides);
        $this->tpl->assign('widgetGallery', $this->gallery);

        // get module settings
        $this->settings = FrontendModel::getModuleSettings('Slideshow');
        
        // should we use the settings per slide or the module settings
        if ($this->settings['settings_per_slide']==='true')
            {               
                // load slideshow settings
                $this->tpl->assign('widgetSlideshowSettings', FrontendSlideshowModel::getGallerySettings($this->data['gallery_id']));   
            }else{
                // load module settings
                $this->tpl->assign('widgetSlideshowSettings', FrontendModel::getModuleSettings('Slideshow'));
            }
    }
}

?>
