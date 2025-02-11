.DEFAULT_GOAL = help

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: analyse
analyse: ## Run static code analysis
	./vendor/bin/phpstan analyse --memory-limit=-1

.PHONY: format
format: ## Format the code using standard Laravel conventions
	./vendor/bin/pint

.PHONY: preview
preview: ## Preview code formatting to be applied to follow standard Laravel conventions
	./vendor/bin/pint --test
