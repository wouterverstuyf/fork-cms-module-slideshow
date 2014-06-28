<?php

namespace Backend\Modules\Slideshow\Actions;

/**
 * This is the configuration-object for the slideshow module
 *
 * @package     backend
 * @subpackage  slideshow
 *
 * @author      Koen Vinken <koen@tagz.be> 
 * @since       1.0
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

use Backend\Core\Engine\Base\ActionAdd as BackendBaseActionAdd;
use Backend\Core\Engine\Authentication as BackendAuthentication;
use Backend\Core\Engine\Form as BackendForm;
use Backend\Core\Engine\Language as BL;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Core\Engine\Meta as BackendMeta;
use Backend\Modules\Slideshow\Engine\Model as BackendSlideshowModel;
use Backend\Modules\Search\Engine\Model as BackendSearchModel;
use Backend\Modules\Tags\Engine\Model as BackendTagsModel;
use Backend\Modules\Users\Engine\Model as BackendUsersModel;

class Add extends BackendBaseActionAdd
{
    /**
     * The available categories
     *
     * @var array
     */
    private $categories;

    /**
     * Execute the action
     *
     * @return  void
     */
    public function execute()
    {
        // call parent, this will probably add some general CSS/JS or other required files
        parent::execute();

        // get all data
        $this->getData();

        // load the form
        $this->loadForm();

        // validate the form
        $this->validateForm();

        // parse
        $this->parse();

        // display the page
        $this->display();
    }

    /**
     * Get the data for a question
     *
     * @return  void
     */
    private function getData()
    {   
        // get categories
        $this->categories = BackendSlideshowModel::getCategoriesForDropdown();
        
        if(empty($this->categories))
        {
            $this->redirect(BackendModel::createURLForAction('add_category'));
        }       
    }

    /**
     * Load the form
     *
     * @return  void
     */
    private function loadForm()
    {
        // create form
        $this->frm = new BackendForm('add');

        // set hidden values
        $rbtHiddenValues[] = array('label' => BL::lbl('Hidden', $this->URL->getModule()), 'value' => 'Y');
        $rbtHiddenValues[] = array('label' => BL::lbl('Published'), 'value' => 'N');

        $this->frm->addText('title', null, null, 'inputText title', 'inputTextError title');
        $this->frm->addEditor('description');
        $this->frm->addImage('filename');
        $this->frm->addDropdown('categories', $this->categories);
        $this->frm->addRadiobutton('hidden', $rbtHiddenValues, 'N');
        $this->frm->addDate('publish_on_date');
        $this->frm->addTime('publish_on_time');     
        $this->frm->addText('width');
        $this->frm->addText('height');
        
        // meta object
        $this->meta = new BackendMeta($this->frm, null, 'title', true);
        
        // set callback for generating a unique URL
        $this->meta->setURLCallback('Backend\Modules\Slideshow\Engine\Model', 'getURLForGallery');

    }


    /**
     * Parse the form
     *
     * @return  void
     */
    protected function parse()
    {
        // call parent
        parent::parse();

        $url = BackendModel::getURLForBlock($this->URL->getModule(), 'detail');

        $url404 = BackendModel::getURL(404);

        // parse additional variables
        if($url404 != $url) $this->tpl->assign('detailURL', SITE_URL . $url);

        // assign categories
        $this->tpl->assign('categories', $this->categories);
    }


    /**
     * Validate the form
     *
     * @return  void
     */
    private function validateForm()
    {
        // is the form submitted?
        if($this->frm->isSubmitted())
        {
            // cleanup the submitted fields, ignore fields that were added by hackers
            $this->frm->cleanupFields();

            // validate fields
            $this->frm->getField('title')->isFilled(BL::err('TitleIsRequired'));

            $this->frm->getField('categories')->isFilled(BL::err('CategoryIsRequired'));

            $this->frm->getField('width')->isFilled(BL::err('WidthIsRequired'));
            
            $this->frm->getField('publish_on_date')->isValid(BL::getError('DateIsInvalid'));
            $this->frm->getField('publish_on_time')->isValid(BL::getError('TimeIsInvalid'));
            
                if($this->frm->getField('filename')->isFilled())
                {
                    // correct extension
                    if($this->frm->getField('filename')->isAllowedExtension(array('jpg', 'jpeg', 'gif', 'png'), BL::err('JPGGIFAndPNGOnly')))
                    {
                        // correct mimetype?
                        $this->frm->getField('filename')->isAllowedMimeType(array('image/gif', 'image/jpg', 'image/jpeg', 'image/png'), BL::err('JPGGIFAndPNGOnly'));
                    }
                }
            
            // validate meta
            $this->meta->validate();

            // no errors?
            if($this->frm->isCorrect())
            {
                // build item
                $item['user_id'] = BackendAuthentication::getUser()->getUserId();
                $item['meta_id'] = $this->meta->save();
                $item['category_id'] = $this->frm->getField('categories')->getValue();
                $item['language'] = BL::getWorkingLanguage();
                $item['title'] = $this->frm->getField('title')->getValue();
                $item['width'] = $this->frm->getField('width')->getValue();
                $item['height'] = $this->frm->getField('height')->getValue();                                           
                $item['description'] = $this->frm->getField('description')->getValue(true);
                
                if($this->frm->getField('filename')->isFilled())
                {
                    // create new filename
                    $filename = rand(0,100000) . $this->frm->getField('filename')->getExtension();
                    $item['filename'] = $filename;
                    
                    // upload the image
                    $this->frm->getField('filename')->moveFile(FRONTEND_FILES_PATH . '/userfiles/images/slideshow/thumbnails/' . $filename);                        
                }

                $item['hidden'] = $this->frm->getField('hidden')->getValue();
                $item['sequence'] = BackendSlideshowModel::getMaximumSlideshowGallerySequence($this->frm->getField('categories')->getValue()) + 1;
                $item['created_on'] = BackendModel::getUTCDate();
                $item['publish_on'] = BackendModel::getUTCDate(null, BackendModel::getUTCTimestamp($this->frm->getField('publish_on_date'), $this->frm->getField('publish_on_time')));              

                // insert the item
                $item['id'] = BackendSlideshowModel::insertGallery($item);

                // add gallery_id to item
                $item['gallery_id'] = $item['id'];

                // insert widget in modules_extras  
                $item['extra_id'] = BackendSlideshowModel::insertWidgetExtras($item);

                // delete gallery_id from array
                unset($item['gallery_id']);

                // update the gallery to insert extra_id
                BackendSlideshowModel::updateGallery($item);
                                
                // get default settings
                $settings = BackendModel::getModuleSettings('Slideshow');

                // remove settings_per_slide from array
                $settings = array_slice($settings, 0,11);
                                
                // add gallery_id to settings
                $settings['gallery_id'] = $item['id'];      
                
                // insert settings
                BackendSlideshowModel::insertGallerySettings($settings);

                // trigger event
                BackendModel::triggerEvent($this->getModule(), 'after_add', array('item' => $item));

                // everything is saved, so redirect to the overview
                $this->redirect(BackendModel::createURLForAction('add_image') . '&report=added&id=' . $item['id']);
            }
        }
    }
}

?>