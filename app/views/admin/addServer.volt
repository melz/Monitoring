{% extends 'admin/base.volt' %}
{% block title %}Add Server{% endblock %}
{% block content %}
    <form role="form" method="POST">
        <h2>Add Server</h2>
        <div class="alert">{{ flash.output() }}</div>
        <p>Please ensure that you have installed the agent on the server before clicking "Create Server":</p>

        <strong>CentOS:</strong>
        <pre id="centos"></pre>

        <strong>Debian/Ubuntu:</strong>
        <pre id="debian"></pre>

        <div style="display: none">
            <label for="key">Key</label>
            {{ form.render('monitor_key', {'class': 'form-control'}) }}
        </div>
        <div style="display: none">
            <label for="password">Password</label>
            {{ form.render('monitor_pass', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="ip">IP Address</label>
            {{ form.render('ip', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="friendly_name">Friendly Name</label>
            {{ form.render('friendly_name', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="friendly_name">Load Threshold</label>
            {{ form.render('load_threshold', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="friendly_name">Use Case</label>
            {{ form.render('type', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="owner">Owner</label>
            {{ form.render('owner', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="alert_owner">Alert Owner?</label>
            {{ form.render('alert_owner', {'class': 'form-control'}) }}
        </div>
        <div class="form-group">
            <label for="alert_admin">Alert Admin?</label>
            {{ form.render('alert_admin', {'class': 'form-control'}) }}
        </div>
        <button class="btn btn-lg btn-primary" type="submit">Create Server</button>
    </form>
{% endblock %}
{% block script %}
    <script>
        var key  = document.getElementById('monitor_key').value;
        var pass = document.getElementById('monitor_pass').value;

        document.getElementById('centos').innerHTML = 'yum -y install bc parted vmstat; mkdir /var/monitor/; wget -O /var/monitor/cron.sh {{ hostname }}/clients/script?key='+key+'&pass='+pass+'"; (crontab -l ; echo "*/5 * * * * bash /var/monitor/cron.sh") | crontab -';
        document.getElementById('debian').innerHTML = 'apt-get install bc parted sysstat; mkdir /var/monitor/; wget -O /var/monitor/cron.sh {{ hostname }}/clients/script?key='+key+'&pass='+pass+'"; (crontab -l ; echo "*/5 * * * * bash /var/monitor/cron.sh") | crontab -';
    </script>
{% endblock %}