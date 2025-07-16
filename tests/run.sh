#!/bin/bash

cd assets/

../../runAIProject.sh --tree --config=../config/ai-all.json ../result/ai-all.txt
../../runAIProject.sh --tree --config=../config/ai-test.json ../result/ai-test.txt
../../runAIProject.sh --tree --config=../config/ai-test-php.json ../result/ai-test-php.txt
../../runAIProject.sh --tree --config=../config/ai-test--php.json ../result/ai-test--php.txt
../../runAIProject.sh --tree --config=../config/ai-test-txt.json ../result/ai-test-txt.txt
