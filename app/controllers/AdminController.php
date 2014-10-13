<?php

use Phalcon\UserPlugin\Auth\Exception as AuthException;

class AdminController extends ControllerBase
{
    const BRUTES_PER_HOUR = 5;

    public function indexAction()
    {
        return $this->response->redirect('/admin/login', true);
    }
    /**
     * Login user
     * @return \Phalcon\Http\ResponseInterface
     */
    public function loginAction()
    {
        // If user is already signed in, redirect
        $identity = $this->session->get('authAdmin');
        if(!is_null($identity))
        {
            return $this->response->redirect('/admin/dashboard', true);
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
                    'email = :email: AND is_admin = 1',
                    'bind' => array(
                        'email' => $this->request->getPost("email", "email")

                    )
                ));
                if(isset($user->id) && password_verify($this->request->getPost("password"), $user->password)){
                    // The user has authenticated successfully
                    $user->setLastLogin($request->getClientAddress());
                    $user->save();
                    $this->session->set('authAdmin', $user->id);
                    return $this->response->redirect('/admin/dashboard', true);
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
        $this->session->remove('authAdmin');
        return $this->response->redirect('/admin/login', true);
    }

    public function dashboardAction()
    {
        $this->checkAuthenticationAdmin();
        $this->view->user = Users::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $this->session->get('authAdmin'))
        ));
    }

    public function serversAction()
    {
        // Initial checks
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();

        // List of all servers
        $serversList = Servers::find();

        // If a server is selected
        if($request->get("sid"))
        {
            $this->view->stats   = $this->getServerStats($request->get("sid"));
            $this->view->details = Servers::findFirst(array(
                "conditions" => "id = :id:",
                "bind"       => array("id" => $request->get("sid"))
            ));
        } else {
            // No server is selected, pick the first one
            if(isset($serversList[0]))
            {
                // If one exists
                $this->view->stats   = $this->getServerStats($serversList[0]->id);
                $this->view->details = Servers::findFirst(array(
                    "conditions" => "id = :id:",
                    "bind"       => array("id" => $serversList[0]->id)
                ));
            } else {
                // No servers exist
            }
        }
        $this->view->serversList = $serversList;
        $this->view->hostname    = sprintf('http%s://%s', isset($_SERVER['HTTPS'])  ? 's' : '', $_SERVER['HTTP_HOST']);
    }

    public function addServerAction()
    {
        // Initial checks
        $this->checkAuthenticationAdmin();
        $form   = new AddServerForm();
        $server = new Servers();

        // If this is a POST request, deal with it:
        if($this->request->isPost()) {
            $form->bind($_POST, $server);
            // If the entry is valid, create the server
            if($form->isValid())
            {
                $server->timestamp = (new \DateTime())->format('Y-m-d H:i:s');
                $server->save();
                $this->flash->error('Server added successfully');
            } else {
                $this->flash->error('Invalid input - please try again');
            }
        }
        $this->view->form        = $form;
        $this->view->hostname    = sprintf('http%s://%s', isset($_SERVER['HTTPS'])  ? 's' : '', $_SERVER['HTTP_HOST']);
    }

    public function deleteServerAction()
    {
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();
        $server    = Servers::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $request->get("sid"))
        ));

        $server->delete();
        return $this->response->redirect('/admin/servers', true);
    }
    public function usersAction()
    {
        $this->checkAuthenticationAdmin();
        $this->view->users = Users::find();
    }
    public function addUserAction()
    {
        // Initial checks
        $this->checkAuthenticationAdmin();
        $form = new AddUserForm();
        $user = new Users();

        // If this is a POST request, deal with it:
        if($this->request->isPost()) {
            $form->bind($_POST, $user);
            // If the entry is valid, create the user
            if($form->isValid())
            {
                // If the details are to be sent to the user
                if($this->request->getPost('send_details'))
                {
                    $config = $this->getDI()->getServices()['config']->resolve();
                    $this->sendEmail('new_account', 'New monitoring account', $user->email, array(
                        'EMAIL'    => $user->email,
                        'PASSWORD' => $user->password,
                        'SERVERS'  => $user->monitor_servers,
                        'URL'   => $config->url
                    ));
                }

                $user->password = $this->security->hash($user->password);
                $user->save();

                $this->flash->error('User added successfully');
            } else {
                $this->flash->error('Invalid input - please try again');
            }
        }
        $this->view->form = $form;
    }
    public function deleteUserAction()
    {
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();

        // Delete servers
        $servers = Servers::find(array(
            "conditions" => "owner = :owner:",
            "bind"       => array("owner" => $request->get("uid"))
        ));
        foreach($servers as $server) $server->delete();

        // Delete user
        $user = Users::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $request->get("uid"))
        ));
        $user->delete();

        return $this->response->redirect('/admin/users', true);
    }
    public function applicationsAction()
    {
        // Initial checks
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();

        // List of all apps
        $appsList = Applications::find();

        // If a app is selected
        if($request->get("aid"))
        {
            $this->view->stats   = $this->getApplicationStats($request->get("aid"));
            $this->view->details = Applications::findFirst(array(
                "conditions" => "id = :id:",
                "bind"       => array("id" => $request->get("aid"))
            ));
        } else {
            // No application is selected, pick the first one
            if(isset($appsList[0]))
            {
                // If one exists
                $this->view->stats   = $this->getApplicationStats($appsList[0]->id);
                $this->view->details = Applications::findFirst(array(
                    "conditions" => "id = :id:",
                    "bind"       => array("id" => $appsList[0]->id)
                ));
            } else {
                // No apps exist
            }
        }
        $this->view->appsList = $appsList;
    }

    public function addApplicationAction()
    {
        // Initial checks
        $this->checkAuthenticationAdmin();
        $form   = new AddApplicationForm();
        $app    = new Applications();

        // If this is a POST request, deal with it:
        if($this->request->isPost()) {
            $form->bind($_POST, $app);
            // If the entry is valid, create the app
            if($form->isValid())
            {
                $app->save();
                $this->flash->error('Application added successfully');
            } else {
                $this->flash->error('Invalid input - please try again');
            }
        }
        $this->view->form = $form;
    }

    public function deleteApplicationAction()
    {
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();
        $server    = Applications::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $request->get("aid"))
        ));

        $server->delete();
        return $this->response->redirect('/admin/applications', true);
    }

    public function emailUsersAction()
    {
        // Initial checks
        $this->checkAuthenticationAdmin();
        $form   = new EmailForm();

        // If this is a POST request, deal with it:
        if($this->request->isPost()) {
            if($this->request->getPost('message') && $this->request->getPost('subject'))
            {
                // Email users
                $users  = Users::find();
                $config = $this->getDI()->getServices()['config']->resolve();
                foreach($users as $user)
                {
                    $postmark = new \Postmark($config->postmark->key, $config->postmark->key);
                    $postmark->to($user->email)
                        ->subject($this->request->getPost('subject'))
                        ->plain_message($this->request->getPost('message'))
                        ->send();
                }
                $this->flash->error('Emails sent');
            } else {
                // Invalid input
                $this->flash->error('Please enter a subject and message');
            }
        }
        $this->view->form = $form;
    }

    /* Policies */
    public function addPolicyAction()
    {
        $this->checkAuthenticationAdmin();

        if($this->request->isPost()) {
            // Create new policy
            $policy             = new Policies();
            $policy->name       = $this->request->getPost('name');
            $policy->what_to_do = $this->request->getPost('what-to-do');
            $policy->owner      = $this->request->getPost('owner');
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
        $this->view->servers = Servers::find();
        $this->view->apps    = Applications::find();
        $this->view->users   = Users::find();
    }

    public function policiesAction()
    {
        $this->view->policies = Policies::find();
    }
    public function deletePolicyAction()
    {
        $this->checkAuthenticationAdmin();
        $request = new Phalcon\Http\Request();
        $server    = Policies::findFirst(array(
            "conditions" => "id = :id:",
            "bind"       => array("id" => $request->get("pid"))
        ));

        $server->delete();
        return $this->response->redirect('/admin/policies', true);
    }
}