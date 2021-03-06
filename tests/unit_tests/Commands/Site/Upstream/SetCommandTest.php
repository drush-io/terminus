<?php

namespace Pantheon\Terminus\UnitTests\Commands\Site;

use Pantheon\Terminus\Collections\Upstreams;
use Pantheon\Terminus\Commands\Site\Upstream\SetCommand;
use Pantheon\Terminus\Models\Upstream;
use Pantheon\Terminus\Models\User;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\Session\Session;
use Pantheon\Terminus\UnitTests\Commands\CommandTestCase;

/**
 * Class SetCommandTest
 * Test suite class for Pantheon\Terminus\Commands\Site\Upstream\SetCommand
 * @package Pantheon\Terminus\UnitTests\Commands\Site\Upstream
 */
class SetCommandTest extends CommandTestCase
{
    /**
     * @var Session
     */
    protected $session;
    /**
     * @var Upstream
     */
    protected $upstream;
    /**
     * @var Upstreams
     */
    protected $upstreams;
    /**
     * @var string[]
     */
    protected $upstream_data;
    /**
     * @var User
     */
    protected $user;
    /**
     * @var Workflow
     */
    protected $workflow;

    /**
     * @inheritdoc
     */
    protected function setup()
    {
        parent::setUp();

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->upstreams = $this->getMockBuilder(Upstreams::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->upstream = $this->getMockBuilder(Upstream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->upstream_data = ['framework' => 'Framework', 'id' => 'upstream_id', 'label' => 'Upstream Name',];

        $this->session->expects($this->once())
            ->method('getUser')
            ->with()
            ->willReturn($this->user);
        $this->user->expects($this->once())
            ->method('getUpstreams')
            ->with()
            ->willReturn($this->upstreams);

        $this->command = new SetCommand($this->getConfig());
        $this->command->setSites($this->sites);
        $this->command->setLogger($this->logger);
        $this->command->setInput($this->input);
        $this->command->setSession($this->session);

        $this->workflow = $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
    }


    /**
     * Exercises the site:upstream:set command
     */
    public function testSet()
    {
        $site_name = 'my-site';
        $upstream_id = $this->upstream_data['id'];

        $this->expectGetUpstream($upstream_id);

        $this->logger->expects($this->at(0))
        ->method('log')->with(
            $this->equalTo('warning'),
            $this->equalTo('This functionality is experimental. Do not use this on production sites.')
        );

        $this->site
            ->method('getName')
            ->willReturn($site_name);

        $this->expectConfirmation();
        $this->site->expects($this->once())
            ->method('setUpstream')
            ->with($upstream_id)
            ->willReturn($this->workflow);

        $this->workflow->expects($this->once())
            ->method('checkProgress')
            ->with()
            ->willReturn(true);

        $this->logger->expects($this->at(1))
          ->method('log')->with(
              $this->equalTo('notice'),
              $this->equalTo('Set upstream for {site} to {upstream}'),
              $this->equalTo(['site' => $site_name, 'upstream' => $this->upstream_data['label']])
          );

        $out = $this->command->set($site_name, $upstream_id);
        $this->assertNull($out);
    }

  /**
   * Exercises the site:upstream:set command when declining the confirmation
   *
   * @todo Remove this when removing TerminusCommand::confirm()
   */
    public function testSetConfirmationDecline()
    {
        $site_name = 'my-site';
        $upstream_id = $this->upstream_data['id'];

        $this->expectGetUpstream($upstream_id);

        $this->logger->expects($this->once())
          ->method('log')->with(
              $this->equalTo('warning'),
              $this->equalTo('This functionality is experimental. Do not use this on production sites.')
          );

        $this->expectConfirmation(false);
        $this->site->expects($this->never())
        ->method('setUpstream');

        $out = $this->command->set($site_name, $upstream_id);
        $this->assertNull($out);
    }

    /**
     * Exercises the site:upstream:set command when Site::delete() fails to ensure message gets through
     */
    public function testSetFailure()
    {
        $site_name = 'my-site';
        $upstream_id = $this->upstream_data['id'];
        $exception_message = 'Error message';

        $this->expectGetUpstream($upstream_id);

        $this->logger->expects($this->once())
        ->method('log')->with(
            $this->equalTo('warning'),
            $this->equalTo('This functionality is experimental. Do not use this on production sites.')
        );

        $this->expectConfirmation();
        $this->site->expects($this->once())
        ->method('setUpstream')
        ->with()
        ->will($this->throwException(new \Exception($exception_message)));

        $this->setExpectedException(\Exception::class, $exception_message);

        $out = $this->command->set($site_name, $upstream_id);
        $this->assertNull($out);
    }

    /**
     * Exercises the site:upstream:set command when the requested upstream cannot be found
     */
    public function testSetUpstreamDNE()
    {
        $site_name = 'my-site';
        $upstream_id = $this->upstream_data['id'];
        $exception_message = 'Error message';

        $this->upstreams->expects($this->once())
            ->method('get')
            ->with($upstream_id)
            ->will($this->throwException(new \Exception($exception_message)));
        $this->logger->expects($this->never())
            ->method('log');
        $this->site->expects($this->never())
            ->method('setUpstream');

        $this->setExpectedException(\Exception::class, $exception_message);

        $out = $this->command->set($site_name, $upstream_id);
        $this->assertNull($out);
    }

    /**
     * @param $upstream_id
     * @return Upstream
     */
    protected function expectGetUpstream($upstream_id)
    {
        $this->upstreams->expects($this->once())
            ->method('get')
            ->with($upstream_id)
            ->willReturn($this->upstream);
        $this->upstream->expects($this->once())
            ->method('get')
            ->with('label')
            ->willReturn($this->upstream_data['label']);
        $this->upstream->id = $this->upstream_data['id'];
        return $this->upstream;
    }
}
