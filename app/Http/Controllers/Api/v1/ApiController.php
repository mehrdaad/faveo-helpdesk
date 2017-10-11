<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Agent\helpdesk\TicketController as CoreTicketController;
use App\Http\Controllers\Controller;
//use Illuminate\Support\Facades\Request as Value;
use App\Http\Requests\helpdesk\TicketRequest;
use App\Model\helpdesk\Agent\Department;
use App\Model\helpdesk\Agent\Teams;
use App\Model\helpdesk\Manage\Help_topic;
use App\Model\helpdesk\Manage\Sla_plan;
use App\Model\helpdesk\Settings\System;
use App\Model\helpdesk\Ticket\Ticket_attachments;
use App\Model\helpdesk\Ticket\Ticket_source;
use App\Model\helpdesk\Ticket\Ticket_Thread;
use App\Model\helpdesk\Ticket\Tickets;
use App\Model\helpdesk\Utility\Priority;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * -----------------------------------------------------------------------------
 * Api Controller
 * -----------------------------------------------------------------------------.
 *
 *
 * @author Vijay Sebastian <vijay.sebastian@ladybirdweb.com>
 * @copyright (c) 2016, Ladybird Web Solution
 * @name Faveo HELPDESK
 *
 * @version v1
 */
class ApiController extends Controller
{
    public $user;
    public $request;
    public $ticket;
    public $model;
    public $thread;
    public $attach;
    public $ticketRequest;
    public $faveoUser;
    public $team;
    public $setting;
    public $helptopic;
    public $slaPlan;
    public $department;
    public $priority;
    public $source;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->middleware('jwt.auth', ['except' => ['register']]);
        $this->middleware('api', ['except' => ['GenerateApiKey']]);

        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $this->user = $user;
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        }

        $ticket = new CoreTicketController();
        $this->ticket = $ticket;

        $model = new Tickets();
        $this->model = $model;

        $thread = new Ticket_Thread();
        $this->thread = $thread;

        $attach = new Ticket_attachments();
        $this->attach = $attach;

        $ticketRequest = new TicketRequest();
        $this->ticketRequest = $ticketRequest;

        $faveoUser = new User();
        $this->faveoUser = $faveoUser;

//        $faveoUser = new User();
//        $this->user = $faveoUser;

        $team = new Teams();
        $this->team = $team;

        $setting = new System();
        $this->setting = $setting;

        $helptopic = new Help_topic();
        $this->helptopic = $helptopic;

        $slaPlan = new Sla_plan();
        $this->slaPlan = $slaPlan;

        $priority = new Priority();
        $this->priority = $priority;

        $department = new Department();
        $this->department = $department;

        $source = new Ticket_source();
        $this->source = $source;
    }

    /**
     * Create Tickets.
     *
     * @method POST
     *
     * @param user_id,subject,body,helptopic,sla,priority,dept
     *
     * @return json
     */
    public function createTicket(\App\Model\helpdesk\Utility\CountryCode $code)
    {
        $v = \Validator::make($this->request->all(), [
                    'user_id'   => 'required|exists:users,id',
                    'subject'   => 'required',
                    'body'      => 'required',
                    'helptopic' => 'required',
        ]);
        if ($v->fails()) {
            $error = $v->errors();

            return response()->json(compact('error'));
        }

        try {
            $user_id = $this->request->input('user_id');
            $user = User::whereId($user_id)->select('email', 'first_name', 'last_name', 'mobile', 'country_code')->first()->toArray();
            $all = $this->request->input() + ['Requester' => $user_id];
            $merged = array_merge($user, $all);
            $request = new \App\Http\Requests\helpdesk\CreateTicketRequest();
            $request->replace($merged);
            \Route::dispatch($request);
            $PhpMailController = new \App\Http\Controllers\Common\PhpMailController();
            $NotificationController = new \App\Http\Controllers\Common\NotificationController();
            $core = new CoreTicketController($PhpMailController, $NotificationController);
            $response = $core->post_newticket($request, $code, true);

            return response()->json(compact('response'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'))
                            ->header('Authenticate: xBasic realm', 'fake');
        }
    }

    /**
     * Reply for the ticket.
     *
     * @param TicketRequest $request
     *
     * @return json
     */
    public function ticketReply()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'ticket_id'     => 'required|exists:tickets,id',
                        'reply_content' => 'required',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $user_id = $this->user->id;
            $attach = $this->request->input('attachments');
            $this->request->merge(['content' => preg_replace('/[ ](?=[^>]*(?:<|$))/', '&nbsp;', nl2br($this->request->get('reply_content')))]);
            $result = $this->ticket->reply($this->request, $this->request->input('ticket_id'), true, true, $user_id, false);
            $result = $result->join('users', 'ticket_thread.user_id', '=', 'users.id')
                    ->select('ticket_thread.*', 'users.first_name as first_name')
                    ->orderBy('ticket_thread.id', 'desc')
                    ->first();

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Edit a ticket.
     *
     * @return json
     */
    public function editTicket()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'ticket_id'       => 'required|exists:tickets,id',
                        'subject'         => 'required',
                        'sla_plan'        => 'required|exists:sla_plan,id',
                        'help_topic'      => 'required|exists:help_topic,id',
                        'ticket_source'   => 'required|exists:ticket_source,id',
                        'ticket_priority' => 'required|exists:ticket_priority,priority_id',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $ticket_id = $this->request->input('ticket_id');
            $result = $this->ticket->ticketEditPost($ticket_id, $this->thread, $this->model);

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Delete The Ticket.
     *
     * @return json
     */
    public function deleteTicket()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'ticket_id' => 'required|exists:tickets,id',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('ticket_id');

            $result = $this->ticket->delete([$id], $this->model);

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get all opened tickets.
     *
     * @return json
     */
    public function openedTickets()
    {
        try {
            //            $result = $this->model->where('status', '=', 1)->where('isanswered', '=', 0)->where('assigned_to', '=', null)->orderBy('id', 'DESC')->get();
//            return response()->json(compact('result'));

            $result = $this->user->join('tickets', function ($join) {
                $join->on('users.id', '=', 'tickets.user_id')
                        ->where('isanswered', '=', 0)->where('status', '=', 1)->whereNull('assigned_to');
            })
                    ->join('department', 'department.id', '=', 'tickets.dept_id')
                    ->join('ticket_priority', 'ticket_priority.priority_id', '=', 'tickets.priority_id')
                    ->join('sla_plan', 'sla_plan.id', '=', 'tickets.sla')
                    ->join('help_topic', 'help_topic.id', '=', 'tickets.help_topic_id')
                    ->join('ticket_status', 'ticket_status.id', '=', 'tickets.status')
                    ->join('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id')
                        ->whereNotNull('title');
                    })
                    ->select('first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', 'title', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name')
                    ->orderBy('ticket_thread.updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->paginate(10)
                    ->toJson();

            return $result;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get Unsigned Tickets.
     *
     * @return json
     */
    public function unassignedTickets()
    {
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $unassigned = $this->user->join('tickets', function ($join) {
                $join->on('users.id', '=', 'tickets.user_id')
                        ->whereNull('assigned_to');
            })
                    ->leftjoin('department', function ($join) {
                        $join->on('department.id', '=', 'tickets.dept_id');
                    })
                    ->join('ticket_status', 'ticket_status.id', '=', 'tickets.status')
                    ->join('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id')
                        ->whereNotNull('title');
                    })
                    ->select(\DB::raw('max(ticket_thread.updated_at) as updated_at'), 'user_name', 'first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', 'title', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name')
                    ->where(function ($query) use ($user) {
                        if ($user->role != 'admin') {
                            $query->where('tickets.dept_id', '=', $user->primary_dpt);
                        }
                    })
                    ->leftJoin('ticket_attachment', 'ticket_attachment.thread_id', '=', 'ticket_thread.id');
            if ($user->role == 'agent') {
                $id = $user->id;
                $dept = \DB::table('department_assign_agents')->where('agent_id', '=', $id)->pluck('department_id')->toArray();
                $unassigned = $unassigned->where(function ($query) use ($dept, $id) {
                    $query->whereIn('tickets.dept_id', $dept)
                            ->orWhere('assigned_to', '=', $id);
                });
            }
            $unassigned = $unassigned->select('ticket_priority.priority_color as priority_color', \DB::raw('substring_index(group_concat(ticket_thread.title order by ticket_thread.id asc) , ",", 1) as title'), 'tickets.duedate as overdue_date', \DB::raw('count(ticket_attachment.id) as attachment'), \DB::raw('max(ticket_thread.updated_at) as updated_at'), 'user_name', 'first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name')
                    ->orderBy('updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->paginate(10)
                    ->toJson();

            return $unassigned;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get closed Tickets.
     *
     * @return json
     */
    public function closeTickets()
    {
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $result = $this->user->join('tickets', function ($join) {
                $join->on('users.id', '=', 'tickets.user_id');
            })
                    ->leftjoin('department', function ($join) {
                        $join->on('department.id', '=', 'tickets.dept_id');
                    })
                    ->join('ticket_status', 'ticket_status.id', '=', 'tickets.status')
                    ->join('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id')
                        ->whereNotNull('title');
                    })
                    ->select(\DB::raw('max(ticket_thread.updated_at) as updated_at'), 'user_name', 'first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', 'title', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name')
                    ->where(function ($query) use ($user) {
                        if ($user->role != 'admin') {
                            $query->where('tickets.dept_id', '=', $user->primary_dpt);
                        }
                    })
                    ->leftJoin('ticket_attachment', 'ticket_attachment.thread_id', '=', 'ticket_thread.id');
            if ($user->role == 'agent') {
                $id = $user->id;
                $dept = \DB::table('department_assign_agents')->where('agent_id', '=', $id)->pluck('department_id')->toArray();
                $result = $result->where(function ($query) use ($dept, $id) {
                    $query->whereIn('tickets.dept_id', $dept)
                            ->orWhere('assigned_to', '=', $id);
                });
            }
            $result = $result->select('tickets.duedate as overdue_date', 'ticket_priority.priority_color as priority_color', \DB::raw('substring_index(group_concat(ticket_thread.title order by ticket_thread.id asc) , ",", 1) as title'), \DB::raw('count(ticket_attachment.id) as attachment'), \DB::raw('max(ticket_thread.updated_at) as updated_at'), 'user_name', 'first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name')
                    ->orderBy('updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->paginate(10)
                    ->toJson();

            return $result;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get All agents.
     *
     * @return json
     */
    public function getAgents()
    {
        try {
            $result = $this->faveoUser->where('role', 'agent')->orWhere('role', 'admin')->where('active', 1)->get();

            return response()->json(compact('result'));
        } catch (Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get All Teams.
     *
     * @return json
     */
    public function getTeams()
    {
        try {
            $result = $this->team->get();

            return response()->json(compact('result'));
        } catch (Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * To assign a ticket.
     *
     * @return json
     */
    public function assignTicket()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'ticket_id' => 'required|exists:tickets,id',
                        'user'      => [
                            'required',
                            \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                                $query->where('role', '!=', 'user');
                            }),
                        ],
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('ticket_id');
            $response = $this->ticket->assign($id);
            if ($response == 1) {
                $result = 'success';

                return response()->json(compact('result'));
            } else {
                return response()->json(compact('response'));
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get all customers.
     *
     * @return json
     */
    public function getCustomers()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'search' => 'required',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $search = $this->request->input('search');
            $result = $this->faveoUser->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->orWhere('user_name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->get();

            return response()->json(compact('result'))
                            ->header('X-Header-One', 'Header Value');
        } catch (Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'))
                            ->header('X-Header-One', 'Header Value');
        }
    }

    /**
     * Get all customers having client_id, client_picture, client_name, client_email, client_phone.
     *
     * @return json
     */
    public function getCustomersWith()
    {
        try {
            $users = $this->user
                    ->leftJoin('user_assign_organization', 'user_assign_organization.user_id', '=', 'users.id')
                    ->leftJoin('organization', 'organization.id', '=', 'user_assign_organization.org_id')
                    ->where('role', 'user')
                    ->select('users.id', 'user_name', 'first_name', 'last_name', 'email', 'phone_number', 'users.profile_pic', 'organization.name AS company', 'users.active')
                    ->paginate(10)
                    ->toJson();

            //dd($users);
            return $users;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'))
                            ->header('Authenticate: xBasic realm', 'fake');
        }
    }

    /**
     * Get a customer by id.
     *
     * @return json
     */
    public function getCustomer()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'user_id' => 'required|exists:users,id',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('user_id');
            $result = $this->faveoUser->where('id', $id)->where('role', 'user')->first();

            return response()->json(compact('result'));
        } catch (Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Search tickets.
     *
     * @return json
     */
    public function searchTicket()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'search' => 'required',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $search = $this->request->input('search');
            $result = $this->thread->select('ticket_id')->where('title', 'like', '%'.$search.'%')->orWhere('body', 'like', '%'.$search.'%')->get();

            return response()->json(compact('result'));
        } catch (Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get threads of a ticket.
     *
     * @return json
     */
    public function ticketThreads()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'id' => 'required|exists:tickets,id',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('id');
            $result = $this->user
                    ->leftjoin('ticket_thread', 'ticket_thread.user_id', '=', 'users.id')
                    ->select('ticket_thread.id', 'ticket_id', 'user_id', 'poster', 'source', 'title', 'body', 'is_internal', 'format', 'ip_address', 'ticket_thread.created_at', 'ticket_thread.updated_at', 'users.first_name', 'users.last_name', 'users.user_name', 'users.email', 'users.profile_pic')
                    ->where('ticket_id', $id)
                    ->get()
                    ->toJson();

            return $result;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Check the url is valid or not.
     *
     * @return json
     */
    public function checkUrl()
    {
        //dd($this->request);
        try {
            $v = \Validator::make($this->request->all(), [
                        'url' => 'required|url',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }

            $url = $this->request->input('url');
            if (!str_is('*/', $url)) {
                $url = str_finish($url, '/');
            }

            $url = $url.'/api/v1/helpdesk/check-url?api_key='.$this->request->input('api_key').'&token='.\Config::get('app.token');
            $result = $this->CallGetApi($url);
            //dd($result);
            return response()->json(compact('result'));
        } catch (\Exception $ex) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Success for currect url.
     *
     * @return string
     */
    public function urlResult()
    {
        return 'success';
    }

    /**
     * Call curl function for Get Method.
     *
     * @param type $url
     *
     * @return type int|string|json
     */
    public function callGetApi($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            echo 'error:'.curl_error($curl);
        }

        return $response;
        curl_close($curl);
    }

    /**
     * Call curl function for POST Method.
     *
     * @param type $url
     * @param type $data
     *
     * @return type int|string|json
     */
    public function callPostApi($url, $data)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            echo 'error:'.curl_error($curl);
        }

        return $response;
        curl_close($curl);
    }

    /**
     * To generate api string.
     *
     * @return type | json
     */
    public function generateApiKey()
    {
        try {
            $set = $this->setting->where('id', '1')->first();
            //dd($set);
            if ($set->api_enable == 1) {
                $key = str_random(32);
                $set->api_key = $key;
                $set->save();
                $result = $set->api_key;

                return response()->json(compact('result'));
            } else {
                $result = 'please enable api';

                return response()->json(compact('result'));
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get help topics.
     *
     * @return json
     */
    public function getHelpTopic()
    {
        try {
            $result = $this->helptopic->get();

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get Sla plans.
     *
     * @return json
     */
    public function getSlaPlan()
    {
        try {
            $result = $this->slaPlan->get();

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get priorities.
     *
     * @return json
     */
    public function getPriority()
    {
        try {
            $result = $this->priority->get();

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Get departments.
     *
     * @return json
     */
    public function getDepartment()
    {
        try {
            $result = $this->department->get();

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Getting the tickets.
     *
     * @return type json
     */
    public function getTickets()
    {
        try {
            $tickets = $this->model->orderBy('created_at', 'desc')->paginate(10);
            $tickets->toJson();

            return $tickets;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Fetching the Inbox details.
     *
     * @return type json
     */
    public function inbox()
    {
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $inbox = $this->user->join('tickets', function ($join) {
                $join->on('users.id', '=', 'tickets.user_id');
            })
                    ->leftjoin('department', function ($join) {
                        $join->on('department.id', '=', 'tickets.dept_id');
                    })
                    ->join('ticket_status', 'ticket_status.id', '=', 'tickets.status')
//                                    ->join('ticket_status_type', 'ticket_status.purpose_of_status', '=', 'ticket_status_type.id')
                    ->leftJoin('ticket_priority', 'ticket_priority.priority_id', '=', 'tickets.priority_id')
                    ->leftJoin('sla_plan', 'sla_plan.id', '=', 'tickets.sla')
                    ->leftJoin('help_topic', 'help_topic.id', '=', 'tickets.help_topic_id')
                    ->leftJoin('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id');
                    })
                    ->leftJoin('ticket_attachment', 'ticket_attachment.thread_id', '=', 'ticket_thread.id')
                    ->where('ticket_status.id', '1');
            if ($user->role == 'agent') {
                $id = $user->id;
                $dept = \DB::table('department_assign_agents')->where('agent_id', '=', $id)->pluck('department_id')->toArray();
                $inbox = $inbox->where(function ($query) use ($dept, $id) {
                    $query->whereIn('tickets.dept_id', $dept)
                            ->orWhere('assigned_to', '=', $id);
                });
            }
            $inbox = $inbox->select(\DB::raw('max(ticket_thread.updated_at) as updated_at'), 'user_name', 'first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', \DB::raw('substring_index(group_concat(ticket_thread.title order by ticket_thread.id asc) , ",", 1) as title'), 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'ticket_priority.priority_color as priority_color', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name', 'department.id as department_id', 'users.primary_dpt as user_dpt', \DB::raw('count(ticket_attachment.id) as attachment'), 'tickets.duedate as overdue_date')
                    ->orderBy('updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->paginate(10)
                    ->toJson();

            return $inbox;
        } catch (\Exception $ex) {
            $error = $ex->getMessage();
            $line = $ex->getLine();
            $file = $ex->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    /**
     * Create internal note.
     *
     * @return type json
     */
    public function internalNote()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'userid'   => 'required|exists:users,id',
                        'ticketid' => 'required|exists:tickets,id',
                        'body'     => 'required',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $userid = $this->request->input('userid');
            $ticketid = $this->request->input('ticketid');

            $body = $this->request->input('body');
            $thread = $this->thread->create(['ticket_id' => $ticketid, 'user_id' => $userid, 'is_internal' => 1, 'body' => $body]);

            return response()->json(compact('thread'));
        } catch (\Exception $ex) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    public function getTrash()
    {
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $trash = $this->user->join('tickets', function ($join) {
                $join->on('users.id', '=', 'tickets.user_id');
            })
                    ->leftjoin('department', function ($join) {
                        $join->on('department.id', '=', 'tickets.dept_id');
                    })
                    ->join('ticket_status', 'ticket_status.id', '=', 'tickets.status')
                    //->join('ticket_status_type', 'ticket_status.purpose_of_status', '=', 'ticket_status_type.id')
                    ->where('ticket_status.id', '5')
                    ->leftJoin('ticket_priority', 'ticket_priority.priority_id', '=', 'tickets.priority_id')
                    ->leftJoin('sla_plan', 'sla_plan.id', '=', 'tickets.sla')
                    ->leftJoin('help_topic', 'help_topic.id', '=', 'tickets.help_topic_id')
                    ->leftJoin('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id');
                    })
                    ->leftJoin('ticket_attachment', 'ticket_attachment.thread_id', '=', 'ticket_thread.id');
            if ($user->role == 'agent') {
                $id = $user->id;
                $dept = \DB::table('department_assign_agents')->where('agent_id', '=', $id)->pluck('department_id')->toArray();
                $trash = $trash->where(function ($query) use ($dept, $id) {
                    $query->whereIn('tickets.dept_id', $dept)
                            ->orWhere('assigned_to', '=', $id);
                });
            }
            $trash = $trash->select('ticket_priority.priority_color as priority_color', \DB::raw('substring_index(group_concat(ticket_thread.title order by ticket_thread.id asc) , ",", 1) as title'), 'tickets.duedate as overdue_date', \DB::raw('count(ticket_attachment.id) as attachment'), \DB::raw('max(ticket_thread.updated_at) as updated_at'), 'user_name', 'first_name', 'last_name', 'email', 'profile_pic', 'ticket_number', 'tickets.id', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name')
                    ->orderBy('updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->paginate(10)
                    ->toJson();

            return $trash;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    public function getMyTicketsAgent()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'user_id' => 'required|exists:users,id',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('user_id');
            if ($this->user->where('id', $id)->first()->role == 'user') {
                $error = 'This user is not an Agent or Admin';

                return response()->json(compact('error'));
            }
            //$user = \JWTAuth::parseToken()->authenticate();
            $result = $this->user->join('tickets', function ($join) use ($id) {
                $join->on('users.id', '=', 'tickets.assigned_to')
                        ->where('status', '=', 1);
                //->where('user_id', '=', $id);
            })
                    ->join('users as client', 'tickets.user_id', '=', 'client.id')
                    ->leftJoin('department', 'department.id', '=', 'tickets.dept_id')
                    ->leftJoin('ticket_priority', 'ticket_priority.priority_id', '=', 'tickets.priority_id')
                    ->leftJoin('sla_plan', 'sla_plan.id', '=', 'tickets.sla')
                    ->leftJoin('help_topic', 'help_topic.id', '=', 'tickets.help_topic_id')
                    ->leftJoin('ticket_status', 'ticket_status.id', '=', 'tickets.status')
                    ->leftJoin('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id');
                    })
                    ->leftJoin('ticket_attachment', 'ticket_attachment.thread_id', '=', 'ticket_thread.id')
                    ->where('users.id', $id)
                    ->select(
                            'ticket_priority.priority_color as priority_color', \DB::raw('substring_index(group_concat(ticket_thread.title order by ticket_thread.id asc) , ",", 1) as title'), 'tickets.duedate as overdue_date', \DB::raw('count(ticket_attachment.id) as attachment'), \DB::raw('max(ticket_thread.updated_at) as updated_at'), 'client.user_name', 'client.first_name', 'client.last_name', 'client.email', 'client.profile_pic', 'ticket_number', 'tickets.id', 'tickets.created_at', 'department.name as department_name', 'ticket_priority.priority as priotity_name', 'sla_plan.name as sla_plan_name', 'help_topic.topic as help_topic_name', 'ticket_status.name as ticket_status_name'
                    )
                    ->orderBy('updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->paginate(10)
                    ->toJson();

            return $result;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    public function getMyTicketsUser()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'user_id' => 'required|exists:users,id',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('user_id');
            if ($this->user->where('id', $id)->first()->role == 'admin' || $this->user->where('id', $id)->first()->role
                    == 'agent') {
                $error = 'This is not a client';

                return response()->json(compact('error'));
            }
            $result = $this->user->join('tickets', function ($join) use ($id) {
                $join->on('users.id', '=', 'tickets.user_id')
                        ->where('user_id', '=', $id);
            })
                    ->join('department', 'department.id', '=', 'tickets.dept_id')
                    ->join('ticket_priority', 'ticket_priority.priority_id', '=', 'tickets.priority_id')
                    ->join('sla_plan', 'sla_plan.id', '=', 'tickets.sla')
                    ->join('help_topic', 'help_topic.id', '=', 'tickets.help_topic_id')
                    ->join('ticket_status', 'ticket_status.id', '=', 'tickets.status')
                    ->join('ticket_thread', function ($join) {
                        $join->on('tickets.id', '=', 'ticket_thread.ticket_id')
                        ->whereNotNull('title');
                    })
                    ->select('ticket_number', 'tickets.id', 'title', 'ticket_status.name as ticket_status_name')
                    ->orderBy('ticket_thread.updated_at', 'desc')
                    ->groupby('tickets.id')
                    ->distinct()
                    ->get()
                    // ->paginate(10)
                    ->toJson();

            return $result;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    public function getTicketById()
    {
        try {
            $v = \Validator::make($this->request->all(), [
                        'id' => 'required|exists:tickets,id',
            ]);

            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $id = $this->request->input('id');
            if (!$this->model->where('id', $id)->first()) {
                $error = 'There is no Ticket as ticket id: '.$id;

                return response()->json(compact('error'));
            }
            $query = $this->user->join('tickets', function ($join) use ($id) {
                $join->on('users.id', '=', 'tickets.user_id')
                        ->where('tickets.id', '=', $id);
            });

            $response = $this->differenciateHelpTopic($query)
                    ->leftJoin('ticket_thread', function ($join) {
                        $join->on('ticket_thread.ticket_id', '=', 'tickets.id')
                        ->where('is_internal', '!=', '1')
                        ->where(function ($query) {
                            return $query->whereNotNull('title')
                                    ->orWhere('title', '=', '');
                        });
                    })
                    ->leftJoin('users as assignee', 'tickets.assigned_to', '=', 'assignee.id')
                    ->leftJoin('department', 'tickets.dept_id', '=', 'department.id')
                    ->leftJoin('ticket_priority', 'tickets.priority_id', '=', 'ticket_priority.priority_id')
                    ->leftJoin('ticket_status', 'tickets.status', '=', 'ticket_status.id')
                    ->leftJoin('sla_plan', 'tickets.sla', '=', 'sla_plan.id')
                    ->leftJoin('ticket_source', 'tickets.source', '=', 'ticket_source.id')
                    ->leftJoin('help_topic', 'tickets.help_topic_id', '=', 'help_topic.id')
                    ->leftJoin('ticket_type', 'tickets.type', '=', 'ticket_type.id');
            //$select = 'users.email','users.user_name','users.first_name','users.last_name','tickets.id','ticket_number','num_sequence','user_id','priority_id','sla','max_open_ticket','captcha','status','lock_by','lock_at','source','isoverdue','reopened','isanswered','is_deleted', 'closed','is_transfer','transfer_at','reopened_at','duedate','closed_at','last_message_at';

            $result = $response->addSelect(
                            'users.email', 'users.user_name', 'users.first_name', 'users.last_name', 'tickets.id', 'ticket_number', 'tickets.user_id', 'ticket_priority.priority_id', 'ticket_priority.priority as priority_name', 'department.name as dept_name', 'ticket_status.name as status_name', 'sla_plan.name as sla_name', 'ticket_source.name as source_name', 'sla_plan.id as sla', 'ticket_status.id as status', 'lock_by', 'lock_at', 'ticket_source.id as source', 'isoverdue', 'reopened', 'isanswered', 'is_deleted', 'closed', 'reopened_at', 'duedate', 'closed_at', 'tickets.created_at', 'tickets.updated_at', 'help_topic.id as helptopic_id', 'help_topic.topic as helptopic_name', 'ticket_thread.title as title', 'ticket_type.id as type', 'ticket_type.name as type_name', 'assignee.id as assignee_id', 'assignee.first_name as assignee_first_name', 'assignee.last_name as assignee_last_name', 'assignee.user_name as assignee_user_name', 'assignee.email as assignee_email'
                    )->first();

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    public function createPagination($array, $perPage)
    {
        try {
            //Get current page form url e.g. &page=6
            $currentPage = LengthAwarePaginator::resolveCurrentPage();

            //Create a new Laravel collection from the array data
            $collection = new Collection($array);

            //Slice the collection to get the items to display in current page
            $currentPageSearchResults = $collection->slice($currentPage * $perPage, $perPage)->all();

            //Create our paginator and pass it to the view
            $paginatedResults = new LengthAwarePaginator($currentPageSearchResults, count($collection), $perPage);

            return $paginatedResults;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (\TokenExpiredException $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }

    public function collaboratorSearch()
    {
        $v = \Validator::make($this->request->all(), [
                    'term' => 'required',
        ]);
        if ($v->fails()) {
            $error = $v->errors();

            return response()->json(compact('error'));
        }

        try {
            $emails = $this->ticket->autosearch();
            //return $emails;
            $user = new User();
            if (count($emails) > 0) {
                foreach ($emails as $key => $email) {
                    $user_model = $user->where('email', $email)->first();
                    //return $user_model;
                    $users[$key]['name'] = $user_model->first_name.' '.$user_model->last_name;
                    $users[$key]['email'] = $email;
                    $users[$key]['avatar'] = $this->avatarUrl($email);
                }
            }
            //return $users;

            return response()->json(compact('users'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        }
    }

    public function avatarUrl($email)
    {
        try {
            $user = new User();
            $user = $user->where('email', $email)->first();
            if ($user->profile_pic) {
                $url = url('uploads/profilepic/'.$user->profile_pic);
            } else {
                $url = \Gravatar::src($email);
            }

            return $url;
        } catch (\Exception $ex) {
            //return $ex->getMessage();
            throw new \Exception($ex->getMessage());
        }
    }

    public function addCollaboratorForTicket()
    {
        try {
            $v = \Validator::make(\Input::get(), [
                        'email'     => 'required|email|unique:users',
                        'ticket_id' => 'required|exists:tickets,id',
                            ]
            );
            if ($v->fails()) {
                $error = $v->messages();

                return response()->json(compact('error'));
            }
            $collaborator = $this->ticket->useradd();

            return response()->json(compact('collaborator'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $ex) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        }
    }

    public function getCollaboratorForTicket()
    {
        try {
            $v = \Validator::make(\Input::get(), [
                        'ticket_id' => 'required',
                            ]
            );
            if ($v->fails()) {
                $error = $v->messages();

                return response()->json(compact('error'));
            }
            $collaborator = $this->ticket->getCollaboratorForTicket();

            return response()->json(compact('collaborator'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $ex) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        }
    }

    public function deleteCollaborator()
    {
        try {
            $v = \Validator::make(\Input::get(), [
                        'ticketid' => 'required|exists:tickets,id',
                        'email'    => 'required|email',
                            ]
            );
            if ($v->fails()) {
                $result = $v->messages();

                return response()->json(compact('result'));
            }
            $collaborator = $this->ticket->userremove();

            return response()->json(compact('collaborator'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        }
    }

    public function dependency()
    {
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $tickets = \DB::table('tickets');
            if ($user->role == 'agent') {
                $id = $user->id;
                $dept = DepartmentAssignAgents::where('agent_id', '=', $id)->pluck('department_id')->toArray();
                $tickets = $tickets->whereIn('tickets.dept_id', $dept)->orWhere('assigned_to', '=', $user->id);
            }
            $department = $this->department->select('name', 'id')->get()->toArray();
            $sla = $this->slaPlan->select('name', 'id')->get()->toArray();
            $staff = $this->user->where('role', '!=', 'user')
                            ->where('ban', '!=', 1)
                            ->where('is_delete', '!=', 1)
                            ->select('email', 'id', 'first_name', 'last_name', 'user_name')
                            ->get()->toArray();
            $team = $this->team->select('name', 'id')->get()->toArray();
            $priority = \DB::table('ticket_priority')
                            ->where('status', '=', 1)
                            ->select('priority', 'priority_id')->get();
            $helptopic = $this->helptopic
                            ->where('status', '=', 1)
                            ->select('topic', 'id')->get()->toArray();
            $status = \DB::table('ticket_status')->select('name', 'id')->get();
            $source = \DB::table('ticket_source')->select('name', 'id')->get();

            $statuses = collect($tickets
                            ->leftJoin('ticket_status', 'tickets.status', '=', 'ticket_status.id')
                            ->select('ticket_status.name as status', \DB::raw('COUNT(tickets.id) as count'))
                            ->groupBy('ticket_status.name')
                            ->get())->transform(function ($item) {
                                return ['name' => ucfirst($item->status), 'count' => $item->count];
                            });
            $unassigned = $this->user->leftJoin('tickets', function ($join) {
                $join->on('users.id', '=', 'tickets.user_id')
                        ->whereNull('assigned_to')->where('status', '=', 1);
            })
                    ->where(function ($query) use ($user) {
                        if ($user->role != 'admin') {
                            $query->where('tickets.dept_id', '=', $user->primary_dpt);
                        }
                    })
                    ->select(\DB::raw('COUNT(tickets.id) as unassined'))
                    ->value('unassined');
            $mytickets = $this->user->leftJoin('tickets', function ($join) use ($user) {
                $join->on('users.id', '=', 'tickets.assigned_to')
                        ->where('tickets.assigned_to', '=', $user->id)->where('status', '=', 1);
            })
                    ->where(function ($query) use ($user) {
                        if ($user->role != 'admin') {
                            $query->where('tickets.dept_id', '=', $user->primary_dpt)->orWhere('assigned_to', '=', $user->id);
                        }
                    })
                    ->select(\DB::raw('COUNT(tickets.id) as my_ticket'))
                    ->value('my_ticket');
            $depend = collect([['name' => 'unassigned', 'count' => $unassigned], ['name' => 'mytickets', 'count' => $mytickets]]);
            $collection = $statuses->merge($depend);
            $result = ['departments'   => $department, 'sla'           => $sla, 'staffs'        => $staff, 'teams'         => $team,
                'priorities'           => $priority, 'helptopics'    => $helptopic, 'status'        => $status, 'sources'       => $source,
                'tickets_count'        => $collection, ];

            return response()->json(compact('result'));
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $line = $e->getLine();
            $file = $e->getFile();

            return response()->json(compact('error', 'file', 'line'));
        }
    }

    public function differenciateHelpTopic($query)
    {
        $ticket = $query->first();
        $check = 'department';
        if ($ticket) {
            if ($ticket->dept_id && $ticket->help_topic_id) {
                return $this->getSystem($check, $query);
            }
            if (!$ticket->dept_id && $ticket->help_topic_id) {
                return $query->select('tickets.help_topic_id');
            }
            if ($ticket->dept_id && !$ticket->help_topic_id) {
                return $query->select('tickets.dept_id');
            }
        }

        return $query;
    }

    public function getSystem($check, $query)
    {
        switch ($check) {
            case 'department':
                return $query->select('tickets.dept_id');
            case 'helpTopic':
                return $query->select('tickets.help_topic_id');
            default:
                return $query->select('tickets.dept_id');
        }
    }

    /**
     * Register a user with username and password.
     *
     * @param Request $request
     *
     * @return type json
     */
    public function register(Request $request)
    {
        try {
            $v = \Validator::make($request->all(), [
                        'email'    => 'required|email|unique:users',
                        'password' => 'required|min:6',
            ]);
            if ($v->fails()) {
                $error = $v->errors();

                return response()->json(compact('error'));
            }
            $auth = $this->user;
            $email = $request->input('email');
            $username = $request->input('email');
            $password = \Hash::make($request->input('password'));
            $role = $request->input('role');
            if (!$role) {
                $role = 'user';
            }
            $user = new User();
            $user->password = $password;
            $user->user_name = $username;
            $user->email = $email;
            $user->role = $role;
            $user->save();

            return response()->json(compact('user'));
        } catch (\Exception $e) {
            $error = $e->getMessage();

            return response()->json(compact('error'));
        }
    }
}
