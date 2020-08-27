<?php

namespace JiraTime\controllers;

use bdk\TinyFrame\Controller;
use JiraTime\JiraHelper;

class Worklog extends Controller
{

    protected $useCache = true;

    /**
     * Constructor
     *
     * @param array|Container $container container
     */
    public function __construct($container = array())
    {
        parent::__construct($container);
        $this->jiraConfig = array_merge($this->jiraConfig, array(
            'jiraHost' => $this->request->getCookieParam('jiraHost'),
            'jiraUser' => $this->request->getCookieParam('username'),
            'jiraPassword' => $this->request->getCookieParam('apiToken') ?: $this->request->getCookieParam('password'),
            'issuesAlways' => array_filter(preg_split('/[, ]+/', $this->request->getCookieParam('issues')), 'strlen'),
            'cookieFile' => $this->dirRoot . '/cookies/' . $this->request->getCookieParam('username') . '.txt',
        ));
        if (!\is_dir($this->dirRoot . '/cookies')) {
            \mkdir($this->dirRoot . '/cookies');
        }
        $this->jiraHelper = new JiraHelper($this->jiraConfig, $this->debug);
    }

    /**
     * edit worklog
     *
     * @return void
     */
    public function actionEditWorklog()
    {
        $vals = $_POST;
        $id = isset($vals['id']) ? $vals['id'] : null;
        $issueKey = $vals['issueKey'];
        $vals['started'] = new \DateTime($vals['start-date'] . ' ' . $vals['start-time']);
        $worklog = $id
            ? $this->jiraHelper->editWorklog($issueKey, $id, $vals)
            : $this->jiraHelper->addWorklog($issueKey, $vals);
        if (!$worklog) {
            $this->ajaxError('fail boat');
        }
        $worklogValues = $this->jiraHelper->worklogToEvent($worklog);
        $worklogValues['issueKey'] = $issueKey;
        $worklogValues['title'] = $issueKey;
        if ($this->useCache) {
            /*
                Update cache
            */
            $cacheKey = 'worklog_' . $issueKey;
            $worklogs = $this->cache->get($cacheKey);
            if ($id) {
                foreach ($worklogs as &$worklog) {
                    if ($worklog['id'] == $id) {
                        $worklog = $worklogValues;
                        break;
                    }
                }
            } else {
                $worklogs[] = $worklogValues;
            }
            $this->cache->set($cacheKey, $worklogs);
        }
        $this->ajaxSuccess(array(
            'worklog' => $worklogValues,
        ));
    }

    /**
     * Fetch Issues & Worklogs
     *
     * @return void
     */
    public function actionWorklog()
    {

        if (!$this->checkCreds()) {
            $this->debug->warn(__METHOD__, 'checkCreds');
            $this->ajaxError('config');
        }

        /*
        $startWorklog = isset($_REQUEST['start']) ? new \DateTime($_REQUEST['start']) : null;
        $endWorklog = isset($_REQUEST['end']) ? new \DateTime($_REQUEST['end']) : null;
        $startIssue = isset($_REQUEST['start']) ? (new \DateTime($_REQUEST['start']))->add(\DateInterval::createFromDateString('-7 days')) : null;
        $endIssue = isset($_REQUEST['end']) ? (new \DateTime($_REQUEST['end']))->add(\DateInterval::createFromDateString('7 days')) : null;
        $clearCache = isset($_REQUEST['clearCache']) && filter_var($_REQUEST['clearCache'], FILTER_VALIDATE_BOOLEAN);
        */

        $startWorklog = $this->request->getParam('start');
        $startWorklog = $startWorklog ? new \DateTime($startWorklog) : null;
        $endWorklog = $this->request->getParam('end');
        $endWorklog = $endWorklog ? new \DateTime($endWorklog) : null;

        $startIssue = $this->request->getParam('start');
        $startIssue = $startIssue ? (new \DateTime($startIssue))->add(\DateInterval::createFromDateString('-7 days')) : null;
        $endIssue = $this->request->getParam('start');
        $endIssue = $endIssue ? (new \DateTime($endIssue))->add(\DateInterval::createFromDateString('7 days')) : null;

        $clearCache = $this->request->getParam('clearCache');
        $clearCache = $clearCache && filter_var($clearCache, FILTER_VALIDATE_BOOLEAN);

        if ($clearCache) {
            $this->debug->warn('clearCache', $clearCache);
            $this->cache->clear();
        }

        $this->ajaxSuccess(array(
            'events' => $this->getWorklogEvents($startIssue, $endIssue, $startWorklog, $endWorklog),
            'issues' => $this->getIssues($startIssue, $endIssue),
        ));
    }

    /**
     * Generate navbar
     *
     * @return string
     */
    public function getNavbar()
    {
        $this->debug->groupCollapsed(__METHOD__);
        $script = <<<'EOD'
        /**
         * Dropdown on hover
         */
        $(function(){
            var timeout = null;
            $('.nav').find('.dropdown-toggle').parent().on('mouseenter mouseleave', function(evt){
                var $nodeTrigger = $(this).find('.dropdown-toggle'),
                    $nodeDrop = $(this).find('.dropdown-menu'),
                    isEnter = $.inArray(evt.type, ['mouseenter']) > -1,
                    isVis = $nodeDrop.is(':visible'),
                    toggle = false;
                if ( isEnter ) {
                    //console.log('isEnter');
                    if ( !isVis )
                        toggle = true;
                } else {
                    //console.log('isExit');
                    if ( isVis ) {
                        toggle = true;
                        $nodeTrigger.blur();    // :hover css rule seems to stick without
                    }
                }
                if ( timeout ) {
                    clearTimeout(timeout);
                }
                if ( toggle ) {
                    timeout = setTimeout(function(){
                        $nodeTrigger.dropdown('toggle');
                    }, 100);
                }
            });
        });
EOD;
        // $this->page->content->update('script', $script);
        $str = '<header class="navbar navbar-inverse" role="banner">
            <div class="container">
                <div class="navbar-header">
                    <a class="navbar-brand" href="{{link /}}"><div class="logo"></div> Jira</a>
                    <button class="navbar-toggle" type="button" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                </div>
                <nav class="collapse navbar-collapse" role="navigation">
                    <ul class="nav navbar-nav navbar-right">
                        <li>
                            <a accesskey="s" href="#modal-settings" class="settings-toggle" data-toggle="modal" role="button" title="settings [s]"><i class="glyphicon glyphicon-cog"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>';
        $this->debug->groupEnd();
        return $str;
    }

    protected function checkCreds()
    {
        if (!$this->request->getCookieParam('username')) {
            return false;
        }
        if (!$this->request->getCookieParam('username')) {
            return false;
        }
        return true;
    }

    /**
     * Get issue list
     *
     * @param DateTime $startIssue [description]
     * @param DateTime $endIssue   [description]
     *
     * @return array key => title
     */
    protected function getIssues($startIssue, $endIssue)
    {
        $this->debug->log('getIssues');
        set_time_limit(0);
        return $this->cache->getSet(
            'issues_' . md5($startIssue->format('Y-m-d H:i:s') . $endIssue->format('Y-m-d H:i:s')),
            function () use ($startIssue, $endIssue) {
                $this->debug->group('set cache');
                $this->debug->time();
                try {
                    $ret = $this->jiraHelper->findMyIssues($startIssue, $endIssue);
                } catch (\Exception $e) {
                    $this->debug->warn('son of a', $e);
                }
                $this->debug->timeEnd();
                if (!$ret['success']) {
                    $this->debug->warn('not ret success', $ret);
                    $this->ajaxError($ret['message']);
                }
                $issues = array();
                foreach ($ret['data']->issues as $issue) {
                    $issueTitle = $issue->key . ': ' . $issue->fields->summary;
                    if (isset($issue->fields->status)) {
                        $issueTitle = $issue->key . ': (' . $issue->fields->status->name . ') ' . $issue->fields->summary;
                    }
                    $issues[$issue->key] = $issueTitle;
                }
                \ksort($issues, SORT_NATURAL);
                $issuesAlways = array();
                $this->debug->log('issuesAlways', $this->jiraConfig['issuesAlways']);
                foreach ($this->jiraConfig['issuesAlways'] as $key) {
                    if (isset($issues[$key])) {
                        $issuesAlways[$key] = $issues[$key];
                        unset($issues[$key]);
                        continue;
                    }
                    $issue = $this->jiraHelper->issueService->get($key);
                    // $this->debug->log('issue', $issue);
                    $issueTitle = $issue->key . ': ' . $issue->fields->summary;
                    if (isset($issue->fields->status)) {
                        $issueTitle = $issue->key . ': (' . $issue->fields->status->name . ') ' . $issue->fields->summary;
                    }
                    $issuesAlways[$issue->key] = $issueTitle;
                }
                $issues = array_merge($issuesAlways, $issues);
                $this->debug->groupEnd();
                return $issues;
            },
            3600
        );
    }

    protected function getWorklogEvents($startIssue, $endIssue, $startWorklog, $endWorklog)
    {
        $this->debug->group(__METHOD__);
        $ret = $this->jiraHelper->findMyIssues($startIssue, $endIssue, true);
        if (!$ret['success']) {
            $this->ajaxError($ret['message']);
        }
        $events = array();
        $startWorklog = $startWorklog->format('Y-m-d H:i:s');
        $endWorklog = $endWorklog->format('Y-m-d H:i:s');
        foreach ($ret['data']->issues as $issue) {
            $issueTitle = $issue->key . ': ' . $issue->fields->summary;
            $this->debug->info($issueTitle);
            if ($this->useCache) {
                // use cache
                $worklogs = $this->cache->getSet(
                    'worklog_' . $issue->key,
                    function () use ($issue, $startWorklog, $endWorklog) {
                        $response =  $this->jiraHelper->getMyWorklogs($issue->key);
                        if (!$response['success']) {
                            $this->ajaxError($response['message']);
                        }
                        return $response['data'];
                    },
                    3600
                );
                foreach ($worklogs as $k => $worklog) {
                    if ($startWorklog && $startWorklog > $worklog['end']) {
                        // $this->debug->warn('>', $startWorklog, $worklog['end']);
                        unset($worklogs[$k]);
                    }
                    if ($endWorklog && $endWorklog < $worklog['start']) {
                        // $this->debug->warn('<', $endWorklog, $worklog['start']);
                        unset($worklogs[$k]);
                    }
                }
            } else {
                $response = $this->jiraHelper->getMyWorklogs($issue->key, $startWorklog, $endWorklog);
                if (!$response['success']) {
                    $this->ajaxError($response['message']);
                }
                $worklogs = $response['data'];
            }
            foreach (\array_keys($worklogs) as $i) {
                $worklogs[$i]['title'] = $issueTitle;
            }
            $events = \array_merge($events, $worklogs);
        }
        $this->debug->table('events', $events);
        $this->debug->groupEnd();
        return $events;
    }
}
