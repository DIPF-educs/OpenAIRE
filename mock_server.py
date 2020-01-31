from flask import Flask, abort, request
import json

app = Flask(__name__)

@app.route('/')
def dummy():
    return "Hello, World"

@app.route('/piwik.php', methods=['POST'])
def piwik():
    app.logger.info('Got: %d bytes' % request.content_length)
    if request.is_json:
        with open('server.json', 'a') as fp:
            fp.write(json.dumps(request.json) + '\n')
        pass
    return {'success': True}

if __name__ == "__main__":
    from gevent.pywsgi import WSGIServer
    http_server = WSGIServer(('', 5000), app)
    http_server.serve_forever()