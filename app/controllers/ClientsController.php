<?php

use Phalcon\UserPlugin\Auth\Exception as AuthException;

class ClientsController extends ControllerBase
{
    const BRUTES_PER_HOUR = 5;

    public function indexAction()
    {
        return $this->response->redirect('/clients/login', true);
    }

    /**
     * Login user
     * @return \Phalcon\Http\ResponseInterface
     */
    public function loginAction()
    {
        // If user is already signed in, redirect
        $identity = $this->session->get('auth');
        if(!is_null($identity))
        {
            return $this->response->redirect('/clients/dashboard', true);
        }

        // If this is a POST request, deal with it:
        if($this->request->isPost()) {
            // Count brutes in last hour, must < 5
            $request = new \Phalcon\Http\Request();
            $brutes = Brutes::find(array(
                "conditions" => "ip = :ip: AND timestamp > :time:",
                "bind"       => array(
                    "ip"   => $request->getClientAddress(),
                    "time" => (new \DateTime("1 hour ago"))->format('Y-m-d H:i:s'))
            ));
            if(count($brutes) < self::BRUTES_PER_HOUR)
            {
                // Find user with appropriate email
                $user = Users::findFirst(array(
                    'email = :email:',
                    'bind' => array(
                        'email' => $this->request->getPost("email", "email")

                    )
                ));
                if(isset($user->id) && password_verify($this->request->getPost("password"), $user->password)){
                    // The user has authenticated successfully
                    $user->setLastLogin($request->getClientAddress());
                    $user->save();
                    $this->session->set('auth', $user->id);
                    return $this->response->redirect('/clients/dashboard', true);
                }

                // The user has failed authentication
                $brute = new Brutes();
                $brute->setBruteDetails($request->getClientAddress());
                $brute->save();
                $this->flash->error("Invalid email address or password");
            } else {
                // This is a brute force user
                $this->flash->error("You are locked out");
            }
        }

        $form = new LoginForm();
        $this->view->form = $form;
    }

    /**
     * Logout user and clear the data from session
     *
     * @return \Phalcon\Http\ResponseInterface
     */
    public function logoutAction()
    {
        $this->session->remove('auth');
        return $this->response->redirect('/clients/login', true);
    }

    public function dashboardAction()
    {
        $this->checkAuthentication();
        $this->view->user = Users::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $this->session->get('auth'))
        ));
    }
    public function serversAction()
    {
        // Initial checks
        $this->checkAuthentication();
        $request = new Phalcon\Http\Request();

        // List of all servers accessible by this user
        $serversList = $this->getServersByUser($this->session->get('auth'));

        // If a server is selected
        if($request->get("sid"))
        {
            // If the user has access to the selected server
            if($this->arrayPropertyExists($request->get("sid"), 'id', $serversList))
            {
                $this->view->stats   = $this->getServerStats($request->get("sid"));
                $this->view->details = Servers::findFirst(array(
                    "conditions" => "id = :id:",
                    "bind"       => array("id" => $request->get("sid"))
                ));
            } else {
                // A server is selected for which the user does not have access
                die("Unauthorised");
            }
        } else {
            // No server is selected, pick the first one
            if(isset($serversList[0]))
            {
                // If one exists
                $this->view->stats  = $this->getServerStats($serversList[0]->id);
                $this->view->details = Servers::findFirst(array(
                    "conditions" => "id = :id:",
                    "bind"       => array("id" => $serversList[0]->id)
                ));
            } else {
                // No servers exist for this user
            }
        }
        $this->view->serversList = $serversList;
        $this->view->hostname    = sprintf('http%s://%s', isset($_SERVER['HTTPS'])  ? 's' : '', $_SERVER['HTTP_HOST']);
    }

    public function addServerAction()
    {
        // Initial checks
        $this->checkAuthentication();
        $form    = new AddServerForm();
        $server  = new Servers();

        // Get current usage
        $user    = Users::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $this->session->get('auth'))
        ));
        $servers = Servers::find(array(
            "conditions" => "owner = :owner:",
            "bind"       => array("owner" => $this->session->get('auth'))
        ));

        // If the user has some servers left to allocate
        if($user->monitor_servers > count($servers))
        {
            // If this is a POST request, deal with it:
            if($this->request->isPost()) {
                $form->bind($_POST, $server);
                // If the entry is valid, create the server
                if($form->isValid())
                {
                    $server->timestamp = (new \DateTime())->format('Y-m-d H:i:s');
                    $server->owner     = $this->session->get('auth');

                    $server->save();
                    $this->flash->error('Server added successfully');
                } else {
                    $this->flash->error('Invalid input - please try again');
                }
            }
            $this->view->form        = $form;
            $this->view->hostname    = sprintf('http%s://%s', isset($_SERVER['HTTPS'])  ? 's' : '', $_SERVER['HTTP_HOST']);
        } else {
            $this->flash->error('You have exhausted your allowance. Please delete a server or upgrade.');
        }
    }

    public function deleteServerAction()
    {
        $this->checkAuthentication();
        $request = new Phalcon\Http\Request();
        $server    = Servers::findFirst(array(
            "conditions" => "id = :id: AND owner = :owner:",
            "bind"       => array("id" => $request->get("sid"), "owner" => $this->session->get('auth'))
        ));

        $server->delete();
        return $this->response->redirect('/clients/servers', true);
    }
    
    /* Applications */
    public function applicationsAction()
    {
        // Initial checks
        $this->checkAuthentication();
        $request = new Phalcon\Http\Request();

        // List of all applications accessible by this user
        $applicationsList = $this->getApplicationsByUser($this->session->get('auth'));

        // If a application is selected
        if($request->get("aid"))
        {
            // If the user has access to the selected application
            if($this->arrayPropertyExists($request->get("aid"), 'id', $applicationsList))
            {
                $this->view->stats   = $this->getApplicationStats($request->get("aid"));
                $this->view->details = Applications::findFirst(array(
                    "conditions" => "id = :id:",
                    "bind"       => array("id" => $request->get("aid"))
                ));
            } else {
                // A application is selected for which the user does not have access
                die("Unauthorised");
            }
        } else {
            // No application is selected, pick the first one
            if(isset($applicationsList[0]))
            {
                // If one exists
                $this->view->stats  = $this->getApplicationStats($applicationsList[0]->id);
                $this->view->details = Applications::findFirst(array(
                    "conditions" => "id = :id:",
                    "bind"       => array("id" => $applicationsList[0]->id)
                ));
            } else {
                // No applications exist for this user
            }
        }
        $this->view->appsList = $applicationsList;
    }

    public function addApplicationAction()
    {
        // Initial checks
        $this->checkAuthentication();
        $form    = new AddApplicationForm($this->session->get('auth'));
        $application  = new Applications();

        // Get current usage
        $user    = Users::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $this->session->get('auth'))
        ));
        $applications = Applications::find(array(
            "conditions" => "owner = :owner:",
            "bind"       => array("owner" => $this->session->get('auth'))
        ));

        // If the user has some applications left to allocate
        if($user->monitor_applications > count($applications))
        {
            // If this is a POST request, deal with it:
            if($this->request->isPost()) {
                $form->bind($_POST, $application);
                // If the entry is valid, create the application
                if($form->isValid())
                {
                    $application->timestamp = (new \DateTime())->format('Y-m-d H:i:s');
                    $application->owner     = $this->session->get('auth');

                    $application->save();
                    $this->flash->error('Application added successfully');
                } else {
                    $this->flash->error('Invalid input - please try again');
                }
            }
            $this->view->form = $form;
        } else {
            $this->flash->error('You have exhausted your allowance. Please delete a application or upgrade.');
        }

    }

    public function deleteApplicationAction()
    {
        $this->checkAuthentication();
        $request = new Phalcon\Http\Request();
        $application    = Applications::findFirst(array(
            "conditions" => "id = :id: AND owner = :owner:",
            "bind"       => array("id" => $request->get("aid"), "owner" => $this->session->get('auth'))
        ));

        $application->delete();
        return $this->response->redirect('/clients/applications', true);
    }

    public function entryAction()
    {
        $request = new \Phalcon\Http\Request();
        $raw     = @file_get_contents('php://input');
        $data    = json_decode(str_replace("\n", '', $raw));

        // Match a server
        $server = Servers::findFirst(array(
            'ip = :ip: AND monitor_key = :key: AND monitor_pass = :pass:',
            'bind' => array(
                'ip'   => $request->getClientAddress(),
                'key'  => $request->get('key'),
                'pass' => $request->get('pass')
            )
        ));

        if($server)
        {
            // Figure out if this is the first entry this hour/day
            $statsHour = StatsHour::find(array(
                "conditions" => "server_id = :id: AND timestamp > :hour:",
                "bind"       => array(
                    "id"  => $server->id,
                    "hour" => (new \DateTime())->format('Y-m-d H')
                )
            ));
            $statsDay = StatsDay::find(array(
                "conditions" => "server_id = :id: AND timestamp > :day:",
                "bind"       => array(
                    "id"  => $server->id,
                    "day" => (new \DateTime())->format('Y-m-d')
                )
            ));

            // If it's the first of the hour
            if(count($statsHour) < 1)
            {
                $statsHour = new StatsHour();
                $this->setMonitoringData($statsHour, $data, $server->id);
                $statsHour->save();
            }

            // If it's the first of the day
            if(count($statsDay) < 1)
            {
                $statsDay = new StatsDay();
                $this->setMonitoringData($statsDay, $data, $server->id);
                $statsDay->save();
            }

            // The unit is always saved
            $statsUnit = new StatsUnit();
            $this->setMonitoringData($statsUnit, $data, $server->id);
            $statsUnit->save();
        } else {
            die("Could not find a matching server");
        }
    }
    public function scriptAction()
    {
        $response     = new \Phalcon\Http\Response();
        $request      = new \Phalcon\Http\Request();
        $content      = file_get_contents(__DIR__.'/../scripts/stat_json.sh');
        $config       = $this->getDI()->getServices()['config']->resolve();
        $replacements = ['__KEY__' => $request->get('key'), '__PASS__' => $request->get('pass'), 'url' => $config->domain];

        foreach($replacements as $old => $new) $content = str_replace($old, $new, $content);

        $response->setHeader("Content-Type", "text/text");
        $response->setContent($content);

        return $response;
    }
    /* Policies */
    public function addPolicyAction()
    {
        $this->checkAuthentication();

        if($this->request->isPost()) {
            // Create new policy
            $policy             = new Policies();
            $policy->name       = $this->request->getPost('name');
            $policy->what_to_do = $this->request->getPost('what-to-do');
            $policy->owner      = $this->session->get('auth');
            $policy->save();

            // Create first condition
            $condition1             = new PolicyConditions();
            $condition1->trigger_id = $policy->id;
            $condition1->app_id     = (strpos($this->request->getPost('when')[0], 'app-') !== FALSE) ? str_replace('app-', '', $this->request->getPost('when')[0]) : null;
            $condition1->server_id  = (strpos($this->request->getPost('when')[0], 'server-') !== FALSE) ? str_replace('server-', '', $this->request->getPost('when')[0]) : null;
            $condition1->metric     = $this->request->getPost('metric')[0];
            $condition1->operator   = $this->request->getPost('operator')[0];
            $condition1->threshold  = $this->request->getPost('value')[0];
            $condition1->save();

            $policy->condition_1    = $condition1->id;

            // Create second condition
            if($this->request->getPost('number-conditions') > 1)
            {
                $condition2             = new PolicyConditions();
                $condition2->trigger_id = $policy->id;
                $condition2->app_id     = (strpos($this->request->getPost('when')[1], 'app-') !== FALSE) ? str_replace('app-', '', $this->request->getPost('when')[1]) : null;
                $condition2->server_id  = (strpos($this->request->getPost('when')[1], 'server-') !== FALSE) ? str_replace('server-', '', $this->request->getPost('when')[1]) : null;
                $condition2->metric     = $this->request->getPost('metric')[1];
                $condition2->operator   = $this->request->getPost('operator')[1];
                $condition2->threshold  = $this->request->getPost('value')[1];
                $condition2->save();

                $policy->operator_1     = $this->request->getPost('conditional')[0];
                $policy->condition_2    = $condition2->id;
            }

            // Create third condition
            if($this->request->getPost('number-conditions') > 2)
            {
                $condition3             = new PolicyConditions();
                $condition3->trigger_id = $policy->id;
                $condition3->app_id     = (strpos($this->request->getPost('when')[2], 'app-') !== FALSE) ? str_replace('app-', '', $this->request->getPost('when')[2]) : null;
                $condition3->server_id  = (strpos($this->request->getPost('when')[2], 'server-') !== FALSE) ? str_replace('server-', '', $this->request->getPost('when')[2]) : null;
                $condition3->metric     = $this->request->getPost('metric')[2];
                $condition3->operator   = $this->request->getPost('operator')[2];
                $condition3->threshold  = $this->request->getPost('value')[2];
                $condition3->save();

                $policy->operator_2     = $this->request->getPost('conditional')[1];
                $policy->condition_3    = $condition3->id;
            }

            // Re-save the policy
            $policy->save();

            $this->flash->error('Policy added');
        }
        $this->view->servers = Servers::find(array(
            "conditions" => "owner = :owner:",
            "bind"       => array("owner" => $this->session->get('auth'))
        ));
        $this->view->apps    = Applications::find(array(
            "conditions" => "owner = :owner:",
            "bind"       => array("owner" => $this->session->get('auth'))
        ));
    }

    public function policiesAction()
    {
        $this->view->policies = Policies::find(array(
            "conditions" => "owner = :owner:",
            "bind"       => array("owner" => $this->session->get('auth'))
        ));
    }
    public function deletePolicyAction()
    {
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();
        $server    = Policies::findFirst(array(
            "conditions" => "id = :id: AND owner = :owner:",
            "bind"       => array("id" => $request->get("pid"), "owner" => $this->session->get('auth'))
        ));

        $server->delete();
        return $this->response->redirect('/clients/policies', true);
    }
}