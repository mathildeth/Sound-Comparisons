# -*- coding: utf-8 -*-
'''
    Tries to reproduce the thing the site currently does when fetching
    /query/templateInfo
    which is delivering a JSON object mapping all mustache templates to their md5 hashes.
    @author JR
'''

import os
import hashlib
import flask

'''
    Relative location of mustache templates
'''
def getMustacheDir():
    return 'static/mustache/'

'''
    @param fname String
    @return sum String
    Compute the md5sum of a file
    by reading it into memory in chunks of 4096 bytes.
'''
def md5(fname):
    hash = hashlib.md5()
    with open(fname, "rb") as f:
        for chunk in iter(lambda: f.read(4096), b""):
            hash.update(chunk)
    return hash.hexdigest()

'''
      @param route String
      @return dict(route+fileName => md5(filename))
      Iterates all files in mustacheDir and creates a dict
      that maps route+fileName to the md5sum of the file at getMustacheDir()+fileName
'''
def getTemplateInfo():
    dir = getMustacheDir()
    templateMap = {}
    for fn in os.listdir(dir):
        file = dir+fn
        templateMap[dir+fn] = md5(file)
    return templateMap

'''
    @param app instance of Flask
    @param queryRoute String
    @param templateRoute String
    Attaches templateInfo logic to queryRoute providing templates at templateRoute.
'''
def returnTemplateInfo():
    tInfo = getTemplateInfo()
    return flask.jsonify(**tInfo)

# Produce test output:
if __name__ == "__main__":
    print(getTemplateInfo(getMustacheDir()))