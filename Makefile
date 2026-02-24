.PHONY: dev prod stop build-dev shell logs restart

# Inicia o ambiente de desenvolvimento local
dev:
	docker-compose -f docker-compose-dev.yml up -d

# Inicia o ambiente de produção (usar apenas no servidor)
prod:
	docker-compose -f docker-compose.yml up -d

# Para todos os containers (checa ambos os arquivos)
stop:
	docker-compose -f docker-compose-dev.yml stop || true
	docker-compose -f docker-compose.yml stop || true

# Reconstrói as imagens e inicia o dev
build-dev:
	docker-compose -f docker-compose-dev.yml up -d --build

# Entra no terminal do container da aplicação (dev)
shell:
	docker exec -it $$(docker ps -q -f name=_app) sh

# Mostra os logs do ambiente dev em tempo real
logs:
	docker-compose -f docker-compose-dev.yml logs -f

# Reinicia o ambiente de desenvolvimento
restart:
	docker-compose -f docker-compose-dev.yml restart
