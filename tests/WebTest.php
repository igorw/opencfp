<?php

use Silex\WebTestCase;

class WebTest extends WebTestCase
{
    function createApplication()
    {
        $bootstrap = new \OpenCFP\Bootstrap(array(
            'database.dsn'      => 'sqlite::memory:',
            'database.user'     => null,
            'database.password' => null,
        ));
        $app = $bootstrap->getApp();

        $app['debug'] = true;
        $app['exception_handler']->disable();
        $app['session.test'] = true;

        return $app;
    }

    function testTalkCreate()
    {
        // $this->app['db']->exec('INSERT INTO users (id, email, password, permissions)');
        // ...

        $client = $this->createClient();

        $crawler = $client->request('GET', '/talk/create');
        $this->assertOk($client);

        $form = $crawler->filter('form')->form();
        $form['title'] = 'foo talk';
        $form['description'] = 'talking about foos, bars and related concepts.';

        $crawler = $client->submit($form);
        $crawler = $client->followRedirect();

        $this->assertOk($client);
        $response = $client->getResponse();
        $this->assertContains('foobar', $response->getContent());
    }

    private function assertOk($client)
    {
        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
    }
}
