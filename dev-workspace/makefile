SHELL := /bin/bash

all: build

help:
	@echo "Available targets:"
	@awk '/^[a-zA-Z0-9_-]+:/ {print "  " $$1}' $(MAKEFILE_LIST)

.PHONY: update

update:
	rm -f ./.cache/.last_image_update_check
	bash ./scripts/services-pull-images.sh "$$@"
