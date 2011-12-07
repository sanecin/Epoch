<?php
namespace Epoch;

class Controller
{
    /**
     * Options array
     * Will include $_GET vars
     */
    public $options = array(
        'format' => 'html'
    );

    public static $customNamespace = 'App';

    public static $url = '';

    protected static $db_settings = array(
        'host'     => 'localhost',
        'user'     => 'wub',
        'password' => 'wub',
        'dbname'   => 'wub'
    );
    
    public $actionable = array();
    
    function __construct($options = array(), $autoRoute = true)
    {
        $this->options = $options + $this->options;
        
        //Will use $this->options to autoRoute.
        if ($autoRoute) {
            $this->autoRoute();
        }
        
        try {
            
            if (!empty($_POST)) {
                $this->handlePost();
            }
            
            $this->run();
        } catch(Exception $e) {
            if (isset($this->options['ajaxupload'])) {
                echo $e->getMessage();
                exit();
            }

            if (false == headers_sent()
                && $code = $e->getCode()) {
                header('HTTP/1.1 '.$code.' '.$e->getMessage());
                header('Status: '.$code.' '.$e->getMessage());
            }

            $this->actionable = $e;
        }
    }
    
    public function autoRoute()
    {
        //Sanatize input.
        if (isset($_GET['model'])) {
            unset($_GET['model']);
        }

        //Start the router.
        $router = new \Epoch\Router(array('baseURL' => \Epoch\Controller::$url, 'srcDir' => dirname(dirname(__FILE__)) . "/" . \Epoch\Controller::$customNamespace . "/"));
        
        //Do the routing.
        $this->options = $router->route($_SERVER['REQUEST_URI'], $this->options);
    }

    public static function setDbSettings($settings = array())
    {
        self::$db_settings = $settings + self::$db_settings;
    }

    public static function getDbSettings()
    {
        return self::$db_settings;
    }

    /**
     * Handle data that is POST'ed to the controller.
     *
     * @return void
     */
    function handlePost()
    {
        if (!isset($_POST['_class'])) {
            // Nothing to do here
            return;
        }
        
        $class = new $_POST['_class']($this->options);
        
        if (isset($_POST['action']) && $_POST['action'] == 'delete') {
            $class->handleDelete($_POST);
        } else {
            $class->handlePost($_POST);
        }
    }
    
    function run()
    {
         if (!isset($this->options['model'])) {
             throw new \Exception('Un-registered view', 404);
         }
         $this->actionable = new $this->options['model']($this->options);
    }
    
    /**
     * Connect to the database and return it
     *
     * @return mysqli
     */
    public static function getDB()
    {
        static $db = false;
        if (!$db) {
            $settings = self::getDbSettings();
            $db = new mysqli($settings['host'], $settings['user'], $settings['password'], $settings['dbname']);
            if (mysqli_connect_error()) {
                throw new \Exception('Database connection error (' . mysqli_connect_errno() . ') '
                        . mysqli_connect_error());
            }
            $db->set_charset('utf8');
        }
        return $db;
    }
    
    static function redirect($url, $exit = true)
    {
        header('Location: '.$url);
        if (false !== $exit) {
            exit($exit);
        }
    }
    
    function render()
    {
        $savvy = new \Epoch\OutputController();
        
        if ($this->options['format'] != 'html') {
            switch($this->options['format']) {
                case 'partial':
                    Savvy_ClassToTemplateMapper::$output_template['App\Controller'] = \Epoch\Controller::$customNamespace . '/Controller-partial';
                    break;
                case 'text':
                case 'json':
                    $savvy->addTemplatePath(dirname(__FILE__).'/www/templates/' . $this->options['format']);
                    header('Content-type:application/json;charset=UTF-8');
                    break;
                default:
                    header('Content-type:text/html;charset=UTF-8');
            }
        }
        
        // Always escape output, use $context->getRaw('var'); to get the raw data.
        $savvy->setEscape('htmlentities');
        
        return $savvy->render($this);
    }
}
