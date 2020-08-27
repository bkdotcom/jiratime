<?php

$this->head->addTag('Icon', '{{uriRoot}}favicon.png');
$this->head->addTag('stylesheet', '{{uriRoot}}js/fullcalendar-3.9.0/fullcalendar.css');
$this->head->addTag('script', '{{uriRoot}}js/fullcalendar-3.9.0/lib/moment.min.js');
$this->head->addTag('script', '{{uriRoot}}js/fullcalendar-3.9.0/fullcalendar.min.js');
$this->head->addTag('script', '//cdnjs.cloudflare.com/ajax/libs/underscore.js/1.9.1/underscore-min.js');
// $this->head->addTag('script', '/JiraTime/js/systemjs-0.21.4/dist/system.js');
$this->head->addTag('script', '{{uriRoot}}content/index.js');

$this->head->addTag('stylesheet', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css');
$this->head->addTag('script', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.full.min.js');
$this->head->addTag('stylesheet', '{{uriRoot}}/css/select2-bootstrap.min.css');

$controlBuilderWorklog = new \bdk\Form\ControlBuilder(array(
    'defaultProps' => array(
        'default' => array(
            'attribsContainer' => array(
                // 'class' => 'row',
            ),
            'attribsLabel' => array(
                'class' => 'col-sm-4',
            ),
            'attribsControls' => array(
                'class' => 'col-sm-8',
            ),
            'idPrefix' => 'worklog',
        ),
    ),
    'theme' => 'bootstrap3',
));
$controlBuilderSettings = new \bdk\Form\ControlBuilder(array(
    'defaultProps' => array(
        'default' => array(
            'attribsContainer' => array(
                // 'class' => 'row',
            ),
            'attribsLabel' => array(
                'class' => 'col-sm-4',
            ),
            'attribsControls' => array(
                'class' => 'col-sm-8',
            ),
            'idPrefix' => 'setting',
        ),
    ),
    'theme' => 'bootstrap3',
));

?>

<style>
#calendar td.fc-day.hover {
    background-color: lightblue;
}
#calendar .current-time {
    cursor: pointer;
}
#calendar .fc-event.hover {
    /*
    background-color: darken(#3a87ad, 20%);
    border-color: darken(@rq-blue, 20%);
    */
}
#calendar .day-total {
    text-align: center;
}
.input-group-btn > .btn-group > .btn {
    float: none;
}
.select2-dropdown.talldrop .select2-results__options {
    max-height: 400px;
}
.select2-dropdown.talldrop .select2-results__option {
    padding-top:3px;
    padding-bottom: 3px;
    font-size: 90%;
    line-height: 1.25em;
}
</style>

<div id="calendar"></div>

<form class="modal fade form-horizontal" id="modal_edit" tabindex="-1" method="post" role="dialog" aria-labelledby="modal-edit-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modal_edit_title">Edit</h4>
            </div>
            <div class="modal-body">

                <?php

                echo $controlBuilderWorklog->build(array(
                    'type' => 'hidden',
                    'name' => 'id',
                ));

                echo $controlBuilderWorklog->build(array(
                    'label' => 'Issue',
                    'name' => 'issueKey',
                    'type' => 'select',
                    'required' => true,
                    'attribs' => array(
                        'data-width'=>'100%',
                    ),
                ));

                echo $controlBuilderWorklog->build(array(
                    'type' => 'date',
                    'label' => 'Start date',
                    'name' => 'start-date',
                    'required' => true,
                ));

                echo $controlBuilderWorklog->build(array(
                    'type' => 'time',
                    'label' => 'Start time',
                    'name' => 'start-time',
                    'required' => true,
                ));

                echo $controlBuilderWorklog->build(array(
                    'type' => 'select',
                    'label' => 'Time Spent',
                    'name' => 'timeSpent',
                    'required' => true,
                    'addSelectOpt' => false,
                    'attribs' => array(
                        'data-default'=>'30m',
                        'data-tags'=>true,
                        'data-width'=>'100%',
                    ),
                    'options' => array('30m', '1h', '1h 30m', '2h'),
                    /*
                    'addonAfter' => '<div class="btn-group" role="group" aria-label="shortcuts">
                            <button tabindex="-1" type="button" class="btn btn-default input-shortcut" data-target="#worklog_timeSpent" data-value="1h">1h</button>
                            <button tabindex="-1" type="button" class="btn btn-default input-shortcut" data-target="#worklog_timeSpent" data-value="1h 30m">1h 30m</button>
                            <button tabindex="-1" type="button" class="btn btn-default input-shortcut" data-target="#worklog_timeSpent" data-value="2h">2h</button>
                        </div>',
                    // 'addonAfter' => '<button type="button" class="btn btn-default">1h</button>',
                    */
                ));

                echo $controlBuilderWorklog->build(array(
                    'type' => 'textarea',
                    'label' => 'Comment',
                    'name' => 'comment',
                ));

                ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</form><!-- /.modal -->

<form class="modal fade form-horizontal" id="modal-settings" tabindex="-1" role="dialog" aria-labelledby="modal-settings-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modal-settings-title">Settings</h4>
            </div>
            <div class="modal-body">

                <?php


                echo $controlBuilderSettings->build(array(
                    'name' => 'jiraHost',
                    'label' => 'Jira Host',
                    'value' => $this->request->getCookieParam('jiraHost'),
                    'type' => 'url',
                    'required' => true,
                    // 'helpBlock' => '<a target="_blank" href="https://community.atlassian.com/t5/Jira-questions/how-to-find-accountid/qaq-p/1111436#:~:text=From%20within%20Jira%2C%20you%20can,own%20user%20avatar%20%2D%3E%20Profile.&text=As%20for%20how%20to%20find,to%20find%20other%20users%20accountId.">How to find AccountId</a>',
                ));

                echo $controlBuilderSettings->build(array(
                    'name' => 'username',
                    'label' => 'Jira Username',
                    'value' => $this->request->getCookieParam('username'),
                    'required' => true,
                    // 'helpBlock' => '<a target="_blank" href="https://community.atlassian.com/t5/Jira-questions/how-to-find-accountid/qaq-p/1111436#:~:text=From%20within%20Jira%2C%20you%20can,own%20user%20avatar%20%2D%3E%20Profile.&text=As%20for%20how%20to%20find,to%20find%20other%20users%20accountId.">How to find AccountId</a>',
                ));

                /*
                echo $controlBuilderSettings->build(array(
                    'name' => 'password',
                    'type' => 'password',
                    'label' => 'Jira Password',
                    'value' => $this->request->getCookieParam('password'),
                    'required' => true,
                ));
                */

                // K3BPFgzqyGwJCHCE1jxY4797
                echo $controlBuilderSettings->build(array(
                    'name' => 'apiToken',
                    'type' => 'password',
                    'label' => 'Api Token',
                    'value' => $this->request->getCookieParam('apiToken'),
                    'required' => true,
                ));

                echo $controlBuilderSettings->build(array(
                    'name' => 'issues',
                    'label' => 'Issues Always',
                    'value' => $this->request->getCookieParam('issues', 'IP-4 PTO-4 SRQPS-188'),
                    'helpBlock' => 'space or comma seperated',
                ));

                ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</form><!-- /.modal -->
