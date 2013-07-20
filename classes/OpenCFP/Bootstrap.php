<?php
namespace OpenCFP;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pimple;
use Twig_Environment;
use Twig_Loader_Filesystem;

class Bootstrap
{
    private $_app;
    private $_config;
    private $_twig;
    private $_purifier;

    function __construct(array $config = null)
    {
        $this->_config = $config;
        $this->initializeAutoLoader();
        $this->_app = $this->getApp();
    }

    function getApp()
    {
        if (isset($this->_app)) {
            return $this->_app;
        }

        // Initialize out Silex app and let's do it
        $app = new \Silex\Application();

        $app['debug'] = true;
        // Register our session provider
        $app->register(new \Silex\Provider\SessionServiceProvider());
        $app->before(function ($request) use ($app) {
            $app['session']->start();
        });

        // Register the Twig provider
        $app->register(new \Silex\Provider\TwigServiceProvider());
        $app['twig'] = $this->getTwig();

        $app['db'] = $this->getDb();

        $app['purifier'] = $this->getPurifier();

        // We're using Sentry, so make it available to app
        $app['sentry'] = $app->share(function() use ($app) {
            $hasher = new \Cartalyst\Sentry\Hashing\NativeHasher;
            $userProvider = new \Cartalyst\Sentry\Users\Eloquent\Provider($hasher);
            $groupProvider = new \Cartalyst\Sentry\Groups\Eloquent\Provider;
            $throttleProvider = new \Cartalyst\Sentry\Throttling\Eloquent\Provider($userProvider);
            $session = new \OpenCFP\SymfonySentrySession($app['session']);
            $cookie = new \Cartalyst\Sentry\Cookies\NativeCookie(array());

            $sentry = new \Cartalyst\Sentry\Sentry(
                $userProvider,
                $groupProvider,
                $throttleProvider,
                $session,
                $cookie
            );

            \Cartalyst\Sentry\Facades\Native\Sentry::setupDatabaseResolver($app['db']);

            return $sentry;
        });

        // Configure our flash messages functionality
        $app->before(function() use ($app) {
            $flash = $app['session']->get('flash');
            $app['session']->set('flash', null);

            if (!empty($flash)) {
                $app['twig']->addGlobal('flash', $flash);
            }
        });

        $app = $this->defineRoutes($app);

        return $app;
    }

    protected function defineRoutes($app)
    {
        $app->get('/', function() use($app) {
            $view = array();
            if ($app['sentry']->check()) {
                $view['user'] = $app['sentry']->getUser();
            }

            $template = $app['twig']->loadTemplate('home.twig');
            return $template->render($view);
        });

        $app->get('/dashboard', 'OpenCFP\DashboardController::indexAction');
        $app->get('/talk/edit/{id}', 'OpenCFP\TalkController::editAction');
        $app->get('/talk/create', 'OpenCFP\TalkController::createAction');
        $app->post('/talk/create', 'OpenCFP\TalkController::processCreateAction');
        $app->post('/talk/update', 'OpenCFP\TalkController::updateAction');
        $app->post('/talk/delete', 'OpenCFP\TalkController::deleteAction');
        $app->get('/login', 'OpenCFP\LoginController::indexAction');
        $app->post('/login', 'OpenCFP\LoginController::processAction');
        $app->get('/logout', 'OpenCFP\LoginController::outAction');
        $app->get('/signup', 'OpenCFP\SignupController::indexAction');
        $app->post('/signup', 'OpenCFP\SignupController::processAction');
        $app->get('/signup/success', 'OpenCFP\SignupController::successAction');
        $app->get('/profile/edit/{id}', 'OpenCFP\ProfileController::editAction');
        $app->post('/profile/edit', 'OpenCFP\ProfileController::processAction');
        $app->get('/profile/change_password', 'OpenCFP\ProfileController::passwordAction');
        $app->post('/profile/change_password', 'OpenCFP\ProfileController::passwordProcessAction');

        return $app;
    }

    /**
     * @param string $configKey
     * @return mixed
     */
    public function getConfig($configKey)
    {
        $config = $this->getConfigContainer();

        if (!isset($config[$configKey])) {
            return null;
        }

        return $this->_config[$configKey];
    }

    public function getConfigContainer()
    {
        if (isset($this->_config)) {
            return $this->_config;
        }

        $loader = new ConfigINIFileLoader(APP_DIR . '/config/config.ini');
        $configData = $loader->load();

        // Place our info into Pimple
        $this->_config = new Pimple();

        foreach ($configData as $category => $info) {
            foreach ($info as $key => $value) {
                $this->_config["{$category}.{$key}"] = $value;
            }
        }

        return $this->_config;
    }

    public function getTwig()
    {
        if (!isset($this->_twig)) {
            // Initialize Twig
            $loader = new Twig_Loader_Filesystem(APP_DIR . $this->getConfig('twig.template_dir'));
            $this->_twig = new Twig_Environment($loader);
            $this->_twig->addGlobal('site', array(
                'url' => $this->getConfig('application.url'),
                'title' => $this->getConfig('application.title')
            ));
        }
        return $this->_twig;
    }

    public function getPurifier() {
        if (!isset($this->_purifier)) {
            $config = \HTMLPurifier_Config::createDefault();
            if ($cachedir = $this->getConfig('htmlpurifier.cachedir')) {
                $config->set('Cache.SerializerPath', $cachedir);
            }
            $this->_purifier = new \HTMLPurifier($config);
        }

        return $this->_purifier;
    }

    /**
     * @return \PDO
     */
    public function getDb()
    {
        $container = $this->getConfigContainer();
        return new \PDO(
            $container['database.dsn'],
            $container['database.user'],
            $container['database.password']
        );
    }

    private function initializeAutoLoader()
    {
        define('APP_DIR', dirname(dirname(__DIR__)));
        if (!file_exists(APP_DIR . '/vendor/autoload.php')) {
            throw new Exception('Autoload file does not exist.  Did you run composer install?');
        }

        require APP_DIR . '/vendor/autoload.php';
    }
}
