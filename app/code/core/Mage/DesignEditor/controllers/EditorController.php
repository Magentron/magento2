<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_DesignEditor
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Controller that allows to display arbitrary page in design editor mode
 */
class Mage_DesignEditor_EditorController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var Mage_DesignEditor_Model_Session
     */
    protected $_session = null;

    /**
     * @var string
     */
    protected $_fullActionName = '';

    /**
     * Enforce admin session with the active design editor mode
     *
     * @return Mage_DesignEditor_EditorController
     */
    public function preDispatch()
    {
        parent::preDispatch();

        $this->_session = Mage::getSingleton('Mage_DesignEditor_Model_Session');
        if (!$this->_session->isDesignEditorActive()) {
            Mage::getSingleton('Mage_Core_Model_Session')->addError(
                $this->__('Design editor is not initialized by administrator.')
            );
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }

        return $this;
    }

    /**
     * Display an arbitrary page by specified page type
     */
    public function pageAction()
    {
        try {
            $handle = $this->getRequest()->getParam('handle');

            // page type format
            if (!$handle || !preg_match('/^[a-z][a-z\d]*(_[a-z][a-z\d]*)*$/i', $handle)) {
                Mage::throwException($this->__('Invalid page handle specified.'));
            }

            // whether such page type exists
            if (!$this->getLayout()->getUpdate()->pageHandleExists($handle)) {
                Mage::throwException($this->__("Specified page handle doesn't exist: '{$handle}'."));
            }

            $this->_fullActionName = $handle;
            $this->addPageLayoutHandles();
            $this->loadLayoutUpdates();
            $this->generateLayoutXml();
            Mage::getModel('Mage_DesignEditor_Model_Layout')->sanitizeLayout($this->getLayout()->getNode());
            $this->generateLayoutBlocks();

            $blockHierarchy = $this->getLayout()->getBlock('design_editor_toolbar_handles_hierarchy');
            if ($blockHierarchy) {
                $blockHierarchy->setSelectedHandle($handle);
            }
            $blockBreadcrumbs = $this->getLayout()->getBlock('design_editor_toolbar_breadcrumbs');
            if ($blockBreadcrumbs) {
                $blockBreadcrumbs->setOmitCurrentPage(true);
            }

            $this->renderLayout();
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBody($e->getMessage());
            $this->getResponse()->setHeader('Content-Type', 'text/plain; charset=UTF-8')->setHttpResponseCode(503);
        }
    }

    /**
     * Hack the "full action name" in order to render emulated layout
     *
     * @param string $delimiter
     * @return string
     */
    public function getFullActionName($delimiter = '_')
    {
        if ($this->_fullActionName) {
            return $this->_fullActionName;
        }
        return parent::getFullActionName($delimiter);
    }

    /**
     * Sets new skin for viewed store and returns customer back to the previous address
     */
    public function skinAction()
    {
        $skin = $this->getRequest()->get('skin');
        $backUrl = $this->_getRefererUrl();

        try {
            $this->_session->setSkin($skin);
        } catch (Mage_Core_Exception $e) {
            $this->_session->addError($e->getMessage());
        }
        $this->getResponse()->setRedirect($backUrl);
    }
}
