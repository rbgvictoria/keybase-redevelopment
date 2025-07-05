#!/bin/bash

git checkout local-deployment
git merge main -m "merge main into local-deployment"

npm run prod

git add -f build_production && git commit -m "Build for deploy"
git subtree push --prefix build_production origin gh-pages

git checkout main