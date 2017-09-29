<?php
namespace FluidTYPO3\Fluidpages\Tests\Unit\Service;

/*
 * This file is part of the FluidTYPO3/Fluidpages project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Fluidpages\Service\ConfigurationService;
use FluidTYPO3\Fluidpages\Service\PageService;
use FluidTYPO3\Fluidpages\Tests\Unit\AbstractTestCase;
use FluidTYPO3\Flux\Form;
use FluidTYPO3\Flux\Service\WorkspacesAwareRecordService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class PageServiceTest
 * @package FluidTYPO3\Fluidpages\Tests\Unit\Service
 */
class PageServiceTest extends AbstractTestCase
{

    /**
     * @return PageService
     */
    protected function getPageService()
    {
        return new PageService();
    }

    /**
     * @test
     */
    public function getPageFlexFormSourceWithZeroUidReturnsNull()
    {
        $this->assertNull($this->getPageService()->getPageFlexFormSource(0));
    }

    /**
     * @test
     */
    public function getPageTemplateConfigurationWithZeroUidReturnsNull()
    {
        $this->assertNull($this->getPageService()->getPageTemplateConfiguration(0));
    }

    /**
     * @dataProvider getPageTemplateConfigurationTestValues
     * @param array $records
     * @param array|NULL $expected
     */
    public function testGetPageTemplateConfiguration(array $records, $expected)
    {
        /** @var WorkspacesAwareRecordService|\PHPUnit_Framework_MockObject_MockObject $service */
        $service = $this->getMockBuilder('FluidTYPO3\\Flux\\Service\\WorkspacesAwareRecordService')->setMethods(array('getSingle'))->getMock();
        foreach ($records as $index => $record) {
            $service->expects($this->at($index))->method('getSingle')->willReturn($record);
        }
        $instance = new PageService();
        $instance->injectWorkspacesAwareRecordService($service);
        $result = $instance->getPageTemplateConfiguration(1);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getPageTemplateConfigurationTestValues()
    {
        $b  = 'backend_layout';
        $bs = 'backend_layout_next_level';
        $a  = 'tx_fed_page_controller_action';
        $as = 'tx_fed_page_controller_action_sub';
        $bfp = 'fluidpages__fluidpages';
        return [
            'no data at all' => [
                [[]],
                null
            ],
            'empty actions' => [
                [
                    [$a => '', $as => '', $b => $bfp, $bs => $bfp]
                ],
                null
            ],
            'controller action on page itself' => [
                [
                    [$a => 'test1->test1', $as => 'test2->test2', $b => $bfp, $bs => $bfp]
                ],
                [$a => 'test1->test1', $as => 'test2->test2']
            ],
            'sub controller action on parent page' => [
                [
                    //pages are listed in reverse order, root level last
                    [$a => '', $b => $bfp, $bs => $bfp],
                    [$as => 'test2->test2', $b => $bfp, $bs => $bfp]
                ],
                [$a => 'test2->test2', $as => 'test2->test2']
            ],
            'no backend layout configured' => [
                [
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => ''],
                ],
                null
            ],
            'backend layout configured only for parent page' => [
                [
                    [$a => 'test1->test1', $as => 'test2->test2', $b => ''  , $bs => ''],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => $bfp, $bs => ''],
                ],
                null
            ],
            'backend layout configured on parent page' => [
                [
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => ''],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => $bfp],
                ],
                [$a => 'test1->test1', $as => 'test2->test2'],
            ],
            'backend layout configured on parent page #2' => [
                [
                    [$a => ''            , $as => ''            , $b => '', $bs => ''],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => $bfp],
                ],
                [$a => 'test2->test2', $as => 'test2->test2'],
            ],
            'different backend layout in between' => [
                [
                    [$a => ''            , $as => ''            , $b => '', $bs => ''],
                    [$a => ''            , $as => ''            , $b => '', $bs => 'templavoila'],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => $bfp],
                ],
                null
            ],
            'self backend layout, but different backend layout in between' => [
                [
                    [$a => ''            , $as => ''            , $b => $bfp, $bs => ''],
                    [$a => ''            , $as => ''            , $b => '', $bs => 'templavoila'],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => $bfp],
                ],
                [$a => 'test2->test2', $as => 'test2->test2']
            ],
            'action and backend layout on different levels: action higher' => [
                [
                    [$a => ''            , $as => ''            , $b => '', $bs => ''],
                    [$a => ''            , $as => ''            , $b => '', $bs => $bfp],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => ''],
                ],
                [$a => 'test2->test2', $as => 'test2->test2']
            ],
            'action and backend layout on different levels: backend layout higher' => [
                [
                    [$a => ''            , $as => ''            , $b => '', $bs => ''],
                    [$a => 'test1->test1', $as => 'test2->test2', $b => '', $bs => ''],
                    [$a => ''            , $as => ''            , $b => '', $bs => $bfp],
                ],
                [$a => 'test2->test2', $as => 'test2->test2']
            ],
        ];
    }

    /**
     * @return void
     */
    public function testGetPageFlexFormSource()
    {
        $record1 = array('pid' => 2, 'uid' => 1);
        $record2 = array('pid' => 0, 'uid' => 3, 'tx_fed_page_flexform' => 'test');
        /** @var WorkspacesAwareRecordService|\PHPUnit_Framework_MockObject_MockObject $service */
        $service = $this->getMockBuilder('FluidTYPO3\\Flux\\Service\\WorkspacesAwareRecordService')->setMethods(array('getSingle'))->getMock();
        $service->expects($this->at(0))->method('getSingle')->with('pages', 'uid,pid,t3ver_oid,tx_fed_page_flexform', 1)->willReturn($record1);
        $service->expects($this->at(1))->method('getSingle')->with('pages', 'uid,pid,t3ver_oid,tx_fed_page_flexform', 2)->willReturn($record2);
        $instance = new PageService();
        $instance->injectWorkspacesAwareRecordService($service);
        $output = $instance->getPageFlexFormSource(1);
        $this->assertEquals('test', $output);
    }

    /**
     * @dataProvider getAvailablePageTemplateFilesTestValues
     * @param string|array $typoScript
     * @param mixed $expected
     */
    public function testGetAvailablePageTemplateFiles($typoScript, $expected)
    {
        /** @var ConfigurationService|\PHPUnit_Framework_MockObject_MockObject $service */
        $service = $this->getMockBuilder(
            'FluidTYPO3\\Fluidpages\\Service\\ConfigurationService'
        )->setMethods(
            array('getPageConfiguration', 'message', 'getFormFromTemplateFile')
        )->getMock();
        $service->expects($this->any())->method('getFormFromTemplateFile')->willReturn(Form::create());
        $service->expects($this->once())->method('getPageConfiguration')->willReturn($typoScript);
        $instance = new PageService();
        $instance->injectConfigurationService($service);
        $instance->injectObjectManager(GeneralUtility::makeInstance(ObjectManager::class));
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces'] = [
            'f' => [
                'TYPO3\\CMS\\Fluid\\ViewHelpers',
                'TYPO3Fluid\\Fluid\\ViewHelpers'
            ]
        ];
        $result = $instance->getAvailablePageTemplateFiles();
        if (null === $expected) {
            $this->assertEmpty($result);
        } else {
            $this->assertNotEmpty($result);
        }
    }

    /**
     * @return array
     */
    public function getAvailablePageTemplateFilesTestValues()
    {
        return array(
            array(array(), null),
            array(array('test' => array('enable' => false)), null),
            array(
                array('fluidpages' => array('templateRootPaths' => array('Dummy'))),
                array('fluidpages' => array('Dummy'))
            ),
            array(
                array('fluidpages' => array('templateRootPaths' => array(ExtensionManagementUtility::extPath('fluidpages', 'Invalid')))),
                array('fluidpages' => null)
            ),
            array(
                array('fluidpages' => array('templateRootPaths' => array(ExtensionManagementUtility::extPath('fluidpages', 'Resources/Private/Templates/')))),
                array('fluidpages' => null)
            ),
        );
    }
}
