{% extends 'clients/base.volt' %}
{% block title %}Servers{% endblock %}
{% block content %}
    {% set periodicities = ['Unit', 'Hour', 'Day'] %}
    <div class="row">
        <div class="col-md-3">
            <ul class="list-group">
                {% for server in serversList %}
                <li class="list-group-item">
                    <div class="pull-right">
                        <a href="#" onclick="deleteServer({{ server.id }})">
                            <span class="octicon octicon-x"></span>
                        </a>
                    </div>
                    <a href="/clients/servers?sid={{ server.id }}">
                        {{ server.friendly_name }}
                    </a>
                </li>
                {% endfor %}
            </ul>
        </div>
        <div class="col-md-9">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs">
                {% for periodicity in periodicities %}
                    <li{% if periodicity == 'Unit' %} class="active"{% endif %}><a href="#{{ periodicity }}" data-toggle="tab">{{ periodicity }}</a></li>
                {% endfor %}
            </ul>
            <br />
            <div class="tab-content">
                {% for periodicity in periodicities %}
                <div class="tab-pane{% if periodicity == 'Unit' %} active{% endif %}" id="{{ periodicity }}">
                    <!-- First stats row -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h3 class="panel-title">CPU Load</h3>
                                </div>
                                <div class="panel-body">
                                    <div id="{{ periodicity }}load" style="overflow: hidden"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Memory Usage</h3>
                                </div>
                                <div class="panel-body">
                                    <div id="{{ periodicity }}memory" style="overflow: hidden"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Secons stats row -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <a href="#" onclick="changeDiskMetric('read')"><span class="badge pull-right">Read</span></a>
                                    <a href="#" onclick="changeDiskMetric('write')"><span class="badge pull-right">Written</span></a>
                                    <a href="#" onclick="changeDiskMetric('inodes')"><span class="badge pull-right">Inodes</span></a>
                                    <a href="#" onclick="changeDiskMetric('space')"><span class="badge pull-right">Space</span></a>
                                    <h3 class="panel-title">Disk Devices</h3>
                                </div>
                                <div class="panel-body">
                                    <div id="{{ periodicity }}read" style="overflow: hidden"></div>
                                    <div id="{{ periodicity }}write" style="overflow: hidden"></div>
                                    <div id="{{ periodicity }}inodes" style="overflow: hidden"></div>
                                    <div id="{{ periodicity }}space" style="overflow: hidden"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <a href="#" onclick="changeNetworkMetric('egress')"><span class="badge pull-right">TX</span></a>
                                    <a href="#" onclick="changeNetworkMetric('ingress')"><span class="badge pull-right">RX</span></a>
                                    <h3 class="panel-title">Network Devices</h3>
                                </div>
                                <div class="panel-body">
                                    <div id="{{ periodicity }}ingress" style="overflow: hidden"></div>
                                    <div id="{{ periodicity }}egress" style="overflow: hidden"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {% endfor %}
            </div>
            <!-- Agent code -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Install Monitoring Agent</h3>
                        </div>
                        <strong>CentOS</strong>
                        <pre style="font-size: 8pt">yum -y install bc parted vmstat; mkdir /var/monitor/; wget -O /var/monitor/cron.sh {{ hostname }}/clients/script?key={{ details.monitor_key }}&pass={{ details.monitor_pass }}"; (crontab -l ; echo "*/5 * * * * bash /var/monitor/cron.sh") | crontab -</pre>
                        <strong>Debian/Ubuntu</strong>
                        <pre style="font-size: 8pt">apt-get install bc parted sysstat; mkdir /var/monitor/; wget -O /var/monitor/cron.sh {{ hostname }}/clients/script?key={{ details.monitor_key }}&pass={{ details.monitor_pass }}"; (crontab -l ; echo "*/5 * * * * bash /var/monitor/cron.sh") | crontab -</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
{% endblock %}
{% block script %}
<script>
/*
 * Unit Metrics
 */
// Colour generator
var palette = new Rickshaw.Color.Palette( { scheme: 'classic9' } );

// Unit stats
var unitGraphs = {
    'load': {
        container: 'Unitload',
        series: [{
            data: [{% for data in stats['Unit']['load'] %}{% for key, stuff in data %}{x: {{ key }}, y: {{ stuff }}}, {% endfor %}{% endfor %}],
            name: 'Load',
            color: palette.color()
        }]
    },
    'memory': {
        container: 'Unitmemory',
        series: [{
            data: [{% for data in stats['Unit']['mem_used_mb'] %}{% for key, stuff in data %}{x: {{ key }}, y: {{ stuff }}}, {% endfor %}{% endfor %}],
            name: '%',
            color: palette.color()
        }]
    },
    'ingress': {
        container: 'Unitingress',
        series: [{% for device, usage in stats['Unit']['networks'] %}{
            data: [{% for ingress in usage['ingress'] %}{% for epoch, value in ingress %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'egress': {
        container: 'Unitegress',
        series: [{% for device, usage in stats['Unit']['networks'] %}{
            data: [{% for egress in usage['egress'] %}{% for epoch, value in egress %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'space': {
        container: 'Unitspace',
        series: [{% for device, usage in stats['Unit']['disks'] %}{
            data: [{% for space in usage['space'] %}{% for epoch, value in space %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} %',
            color: palette.color()
        },{% endfor %}]
    },
    'inodes': {
        container: 'Unitinodes',
        series: [{% for device, usage in stats['Unit']['disks'] %}{
            data: [{% for inodes in usage['inode'] %}{% for epoch, value in inodes %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} %',
            color: palette.color()
        },{% endfor %}]
    },
    'read': {
        container: 'Unitread',
        series: [{% for device, usage in stats['Unit']['disks'] %}{
            data: [{% for read in usage['read'] %}{% for epoch, value in read %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'write': {
        container: 'Unitwrite',
        series: [{% for device, usage in stats['Unit']['disks'] %}{
            data: [{% for write in usage['write'] %}{% for epoch, value in write %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    }
};
// Draw graphs
drawGraph(unitGraphs['load']['container'], unitGraphs['load']['series']);
drawGraph(unitGraphs['memory']['container'], unitGraphs['memory']['series']);
drawGraph(unitGraphs['ingress']['container'], unitGraphs['ingress']['series']);
drawGraph(unitGraphs['egress']['container'], unitGraphs['egress']['series']);
drawGraph(unitGraphs['space']['container'], unitGraphs['space']['series']);
drawGraph(unitGraphs['inodes']['container'], unitGraphs['inodes']['series']);
drawGraph(unitGraphs['read']['container'], unitGraphs['read']['series']);
drawGraph(unitGraphs['write']['container'], unitGraphs['write']['series']);

/*
 * Hour Metrics
 */
// Colour generator
var palette = new Rickshaw.Color.Palette( { scheme: 'classic9' } );

// Hour stats
var hourGraphs = {
    'load': {
        container: 'Hourload',
        series: [{
            data: [{% for data in stats['Hour']['load'] %}{% for key, stuff in data %}{x: {{ key }}, y: {{ stuff }}}, {% endfor %}{% endfor %}],
            name: 'Load',
            color: palette.color()
        }]
    },
    'memory': {
        container: 'Hourmemory',
        series: [{
            data: [{% for data in stats['Hour']['mem_used_mb'] %}{% for key, stuff in data %}{x: {{ key }}, y: {{ stuff }}}, {% endfor %}{% endfor %}],
            name: '%',
            color: palette.color()
        }]
    },
    'ingress': {
        container: 'Houringress',
        series: [{% for device, usage in stats['Hour']['networks'] %}{
            data: [{% for ingress in usage['ingress'] %}{% for epoch, value in ingress %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'egress': {
        container: 'Houregress',
        series: [{% for device, usage in stats['Hour']['networks'] %}{
            data: [{% for egress in usage['egress'] %}{% for epoch, value in egress %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'space': {
        container: 'Hourspace',
        series: [{% for device, usage in stats['Hour']['disks'] %}{
            data: [{% for space in usage['space'] %}{% for epoch, value in space %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'inodes': {
        container: 'Hourinodes',
        series: [{% for device, usage in stats['Hour']['disks'] %}{
            data: [{% for inodes in usage['inode'] %}{% for epoch, value in inodes %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} %',
            color: palette.color()
        },{% endfor %}]
    },
    'read': {
        container: 'Hourread',
        series: [{% for device, usage in stats['Hour']['disks'] %}{
            data: [{% for read in usage['read'] %}{% for epoch, value in read %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'write': {
        container: 'Hourwrite',
        series: [{% for device, usage in stats['Hour']['disks'] %}{
            data: [{% for write in usage['write'] %}{% for epoch, value in write %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    }
};
// Draw graphs
drawGraph(hourGraphs['load']['container'], hourGraphs['load']['series']);
drawGraph(hourGraphs['memory']['container'], hourGraphs['memory']['series']);
drawGraph(hourGraphs['ingress']['container'], hourGraphs['ingress']['series']);
drawGraph(hourGraphs['egress']['container'], hourGraphs['egress']['series']);
drawGraph(hourGraphs['space']['container'], hourGraphs['space']['series']);
drawGraph(hourGraphs['inodes']['container'], hourGraphs['inodes']['series']);
drawGraph(hourGraphs['read']['container'], hourGraphs['read']['series']);
drawGraph(hourGraphs['write']['container'], hourGraphs['write']['series']);
/*
 * Day Metrics
 */
// Colour generator
var palette = new Rickshaw.Color.Palette( { scheme: 'classic9' } );

// Day stats
var dayGraphs = {
    'load': {
        container: 'Dayload',
        series: [{
            data: [{% for data in stats['Day']['load'] %}{% for key, stuff in data %}{x: {{ key }}, y: {{ stuff }}}, {% endfor %}{% endfor %}],
            name: 'Load',
            color: palette.color()
        }]
    },
    'memory': {
        container: 'Daymemory',
        series: [{
            data: [{% for data in stats['Day']['mem_used_mb'] %}{% for key, stuff in data %}{x: {{ key }}, y: {{ stuff }}}, {% endfor %}{% endfor %}],
            name: '%',
            color: palette.color()
        }]
    },
    'ingress': {
        container: 'Dayingress',
        series: [{% for device, usage in stats['Day']['networks'] %}{
            data: [{% for ingress in usage['ingress'] %}{% for epoch, value in ingress %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'egress': {
        container: 'Dayegress',
        series: [{% for device, usage in stats['Day']['networks'] %}{
            data: [{% for egress in usage['egress'] %}{% for epoch, value in egress %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'space': {
        container: 'Dayspace',
        series: [{% for device, usage in stats['Day']['disks'] %}{
            data: [{% for space in usage['space'] %}{% for epoch, value in space %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} %',
            color: palette.color()
        },{% endfor %}]
    },
    'inodes': {
        container: 'Dayinodes',
        series: [{% for device, usage in stats['Day']['disks'] %}{
            data: [{% for inodes in usage['inode'] %}{% for epoch, value in inodes %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} %',
            color: palette.color()
        },{% endfor %}]
    },
    'read': {
        container: 'Dayread',
        series: [{% for device, usage in stats['Day']['disks'] %}{
            data: [{% for read in usage['read'] %}{% for epoch, value in read %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    },
    'write': {
        container: 'Daywrite',
        series: [{% for device, usage in stats['Day']['disks'] %}{
            data: [{% for write in usage['write'] %}{% for epoch, value in write %}{x: {{ epoch }}, y: {{ value }}}, {% endfor %}{% endfor %}],
            name: '{{ device }} MB',
            color: palette.color()
        },{% endfor %}]
    }
};
// Draw graphs
drawGraph(dayGraphs['load']['container'], dayGraphs['load']['series']);
drawGraph(dayGraphs['memory']['container'], dayGraphs['memory']['series']);
drawGraph(dayGraphs['ingress']['container'], dayGraphs['ingress']['series']);
drawGraph(dayGraphs['egress']['container'], dayGraphs['egress']['series']);
drawGraph(dayGraphs['space']['container'], dayGraphs['space']['series']);
drawGraph(dayGraphs['inodes']['container'], dayGraphs['inodes']['series']);
drawGraph(dayGraphs['read']['container'], dayGraphs['read']['series']);
drawGraph(dayGraphs['write']['container'], dayGraphs['write']['series']);

    // Set visible disk and network metrics
    changeDiskMetric('space'); changeNetworkMetric('ingress');

    // TODO: Move these into a JS file
    function drawGraph(container, dataSet)
    {
        // instantiate our graph!
        var graph = new Rickshaw.Graph( {
            element: document.getElementById(container),
            renderer: 'line',
            series: dataSet
        } );

        graph.render();

        var hoverDetail = new Rickshaw.Graph.HoverDetail( {
            graph: graph
        } );

        var axes = new Rickshaw.Graph.Axis.Time( {
            graph: graph
        } );
        axes.render();
    }
    function changeDiskMetric(metric)
    {
        var containers = ['read', 'write', 'inodes', 'space'];
        var periodicities = ['Unit', 'Hour', 'Day'];
        for(var i=0; i<periodicities.length; i++)
        {
            for(var j=0; j<containers.length; j++)
            {
                document.getElementById(periodicities[i]+containers[j]).style.display = (metric == containers[j]) ? "block" : "none";
            }
        }
    }
    function changeNetworkMetric(metric)
    {
        var containers = ['ingress', 'egress'];
        var periodicities = ['Unit', 'Hour', 'Day'];
        for(var i=0; i<periodicities.length; i++)
        {
            for(var j=0; j<containers.length; j++)
            {
                document.getElementById(periodicities[i]+containers[j]).style.display = (metric == containers[j]) ? "block" : "none";
            }
        }
    }
    function deleteServer(sid)
    {
        if (confirm("Are you sure you want to delete server ID "+sid+"?") == true) {
            window.location.replace("/clients/deleteServer?sid="+sid);
        }
    }
</script>
{% endblock %}