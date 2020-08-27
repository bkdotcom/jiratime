<?php

/**
 * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.10.2/
 */

namespace JiraTime;

use bdk\Debug;
use bdk\Debug\Psr3\Logger;
use Exception;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Auth\AuthService;
use JiraRestApi\Issue\Issue;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Worklog;
use JiraRestApi\JiraException;
use JiraRestApi\User\UserService;

/**
 * Make life a bit easier
 */
class JiraHelper
{

    public $debug;
    protected $config;
    protected $currentUser = null;
    protected $worklogProps = array('comment','started','startedDateTime','timeSpent','timeSpentSeconds','visibility');

    /**
     * Constructor
     *
     * @param array $config [description]
     * @param Debug $debug  Debug instance
     */
    public function __construct($config, Debug $debug)
    {
        // accountId
        // $config['jiraUser'] = 'bradley@repairq.io';
        $this->config = \array_merge(array(
            'cookieAuthEnabled' => false,
            'jiraHost' => '',
            'jiraLogEnabled' => true,
            'jiraLogFile' => null,
            'jiraPassword' => '',
            'jiraUser' => '',
            'useV3RestApi' => false,
        ), $config);
        $this->debug = $debug;

        $this->debug->eventManager->subscribe('errorHandler.error', function ($e) {
            /*
                JiraRestClient has some issues... lets ignore em
            */
            if ($e['category'] !== 'fatal') {
                $filesIgnore = array(
                    'vendor/lesstif/php-jira-rest-client/src/Issue/Worklog.php',
                    'vendor/netresearch/jsonmapper/src/JsonMapper.php',
                );
                foreach ($filesIgnore as $file) {
                    if (\strpos($e['file'], $file)) {
                        $e->stopPropagation();
                        break;
                    }
                }
            }
        }, 1);
    }

    /**
     * Magic getter
     *
     * @param [type] $property [description]
     *
     * @return [type] [description]
     */
    public function __get($property)
    {
        $services = array(
            'logger' => function () {
                return new Logger();
            },
            'authService' => function () {
                return new AuthService(
                    new ArrayConfiguration($this->config),
                    $this->logger
                );
            },
            'issueService' => function () {
                return new IssueService(
                    new ArrayConfiguration($this->config),
                    $this->logger
                );
            },
            'userService' => function () {
                return new UserService(
                    new ArrayConfiguration($this->config),
                    $this->logger
                );
            },
        );
        if (isset($services[$property])) {
            $val = $services[$property];
            if (\is_object($val) && \method_exists($val, '__invoke')) {
                $val = $val($this);
            }
            $this->{$property} = $val;
            return $val;
        }
        $getter = 'get' . \ucfirst($property);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
        return null;
    }

    /**
     * [addWorklog description]
     *
     * @param string $issueKey issue key
     * @param array  $vals     values
     *
     * @return JiraRestApi\Issue\Worklog
     */
    public function addWorklog($issueKey, $vals = array())
    {
        $vals = \array_intersect_key($vals, \array_flip($this->worklogProps));
        $vals = \array_merge(array(
            'started' => \date('Y-m-d H:i:s'),
            'timeSpent' => '30m',
            'comment' => null,
        ), $vals);
        try {
            $worklog = new Worklog();
            foreach ($vals as $k => $v) {
                $method = 'set' . \ucfirst($k);
                $worklog->{$method}($v);
            }
            return $this->issueService->addWorklog($issueKey, $worklog);
        } catch (Exception $e) {
            $this->debug->error($e);
        }
    }

    /**
     * edit a worklog
     *
     * @param string  $issueKey  issue key
     * @param integer $worklogId worklog id
     * @param array   $vals      values
     *
     * @return JiraRestApi\Issue\Worklog
     */
    public function editWorklog($issueKey, $worklogId, $vals)
    {
        try {
            $worklog = $this->issueService->getWorklogById($issueKey, $worklogId);
            $vals = \array_intersect_key($vals, \array_flip($this->worklogProps));
            foreach ($vals as $k => $v) {
                $method = 'set' . \ucfirst($k);
                $worklog->{$method}($v);
            }
            if (isset($vals['timeSpent'])) {
                $worklog->timeSpentSeconds = null;
            } elseif (isset($vals['timeSpentSeconds'])) {
                $worklog->timeSpent = null;
            }
            return $this->issueService->editWorklog($issueKey, $worklog, $worklogId);
        } catch (Exception $e) {
            $this->debug->error($e);
            return false;
        }
    }

    /**
     * getCurrentUser
     *
     * @return JiraRestApi\Auth\CurrentUser
     */
    public function getCurrentUser()
    {
        // return $this->authService->getCurrentUser();
        if (!$this->currentUser) {
            $this->currentUser = $this->userService->getMyself();
        }
        return $this->currentUser;
    }

    /**
     * [getMyWorklogs description]
     *
     * @param string $issueKey issue key
     * @param string $start    date
     * @param string $end      date
     *
     * @return array
     */
    public function getMyWorklogs($issueKey, $start = null, $end = null)
    {
        $this->debug->warn(__METHOD__);
        $userObj = $this->getCurrentUser();
        $worklogs = array();
        try {
            $worklogObjects = $this->issueService->getWorklog($issueKey)->worklogs;
        } catch (JiraException $e) {
            $this->debug->log('exception 1', $e);
            return array(
                'success' => false,
                'exception' => $e,
                'message' => $e->getCode() == 401 ? 'config' : 'unknown',
            );
        }
        if ($start instanceof \DateTime) {
            $start = $start->format('Y-m-d');
        }
        if ($end instanceof \DateTime) {
            $end = $end->format('Y-m-d');
        }
        foreach ($worklogObjects as $worklog) {
            if (isset($worklog->author['accountId'])) {
                if ($worklog->author['accountId'] !== $userObj->accountId) {
                    continue;
                }
            } elseif (isset($worklog->author['emailAddress'])) {
                if ($worklog->author['emailAddress'] !== $userObj->emailAddress) {
                    continue;
                }
            } elseif (isset($worklog->author['displayName'])) {
                if ($worklog->author['displayName'] !== $userObj->displayName) {
                    continue;
                }
            } else {
                $this->debug->warn('no accountId, emailAddress, or displayName', $worklog->author);
            }
            $worklog = $this->worklogToEvent($worklog);
            if ($start && $start > $worklog['end']) {
                continue;
            }
            if ($end && $end < $worklog['start']) {
                continue;
            }
            $worklog['issueKey'] = $issueKey;
            $worklogs[] = $worklog;
        }
        return array(
            'success' => true,
            'data' => $worklogs,
        );
    }

    /**
     * [findMyIssues description]
     *
     * @param DateTime|string $startDate       [description]
     * @param Datetime|string $endDate         [description]
     * @param boolean         $onlyWithWorklog [description]
     *
     * @return Issue[]
     */
    public function findMyIssues($startDate = null, $endDate = null, $onlyWithWorklog = false)
    {
        $this->debug->group(__METHOD__, $startDate, $endDate, $onlyWithWorklog);
        $updatedDate = array();
        $worklogDate = array();
        if ($startDate) {
            if ($startDate instanceof \DateTime) {
                $startDate = $startDate->format("Y-m-d");
            }
            $updatedDate[] = 'updatedDate >= "' . $startDate . '"';
            $worklogDate[] = 'worklogDate > "' . $startDate . '"';
        }
        if ($endDate) {
            if ($endDate instanceof \DateTime) {
                $endDate = $endDate->format("Y-m-d");
            }
            $updatedDate[] = 'updatedDate <= "' . $endDate . '"';
            $worklogDate[] = 'worklogDate < "' . $endDate . '"';
        }
        if ($onlyWithWorklog) {
            $jql = '(' . ($worklogDate ? \implode(' AND ', $worklogDate) . ' AND ' : '') . 'worklogAuthor = currentUser())';
            $jql .= ' ORDER BY created DESC';
        } else {
            $currentUser = $this->getCurrentUser();
            $accountId = $currentUser->accountId;
            $jql = '(assignee was ' . $accountId . ' OR reporter was ' . $accountId . ' OR status changed by ' . $accountId . ')';
            if ($updatedDate) {
                $jql .= ' AND ((' . \implode(' AND ', $updatedDate) . ') OR Resolution is EMPTY)';
                $jql .= ' OR (' . \implode(' AND ', $worklogDate) . ' AND worklogAuthor = currentUser())';
            }
            $jql .= ' ORDER BY created DESC';
        }
        // $this->debug->info('jql', $jql);
        try {
            $response = $this->issueService->search($jql, 0, 100, array("*all"));
            // $this->debug->log('response', $response);
            // $this->debug->info('returning %s of %s', \count($response->issues), $response->total);
        } catch (Exception $e) {
            $this->debug->warn('Exception');
            return array(
                'success' => false,
                'exception' => $e,
                'message' => $e->getCode() == 401 ? 'config' : 'unknown',
            );
        }
        $this->debug->groupEnd();
        return array(
            'success' => true,
            'data' => $response,
        );
    }

    /**
     * [worklogToEvent description]
     *
     * @param JiraRestApi\Issue\Worklog $worklog worklog
     *
     * @return array
     */
    public function worklogToEvent(Worklog $worklog)
    {
        // $this->debug->log('worklog', $worklog);
        $start = new \DateTimeImmutable($worklog->started);
        if (!$start->format('I')) {
            // not in DST
            // jira is dumb
            $start = $start->add(\DateInterval::createFromDateString('-1 hour'));
        }
        $end = $start->add(\DateInterval::createFromDateString($worklog->timeSpentSeconds . ' seconds'));
        return array(
            'id' => $worklog->id,
            'issueKey' => '',
            'title' => '',
            'comment' => $worklog->comment,
            'start' => $start->format('Y-m-d\TH:i:s'),
            'end' => $end->format('Y-m-d\TH:i:s'),
        );
    }
}
