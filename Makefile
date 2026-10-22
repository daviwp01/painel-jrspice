.PHONY: dev prod stop build-dev shell logs restart deploy-prod

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

# ── Produção (roda apenas no servidor) ───────────────────────────────────────
# Sequência segura de deploy. O restart do web NÃO é opcional: o nginx resolve
# o IP do upstream (app) uma única vez ao subir, e recriar o container do app
# muda esse IP — sem reiniciar o nginx o site passa a responder 502.
deploy-prod:
	docker compose -f docker-compose.yml build app worker
	docker compose -f docker-compose.yml up -d app worker
	docker compose -f docker-compose.yml restart web

# Baixar banco de dados
scp "root@143.110.205.180:/srv/painel-jrspice/backup_*.sql.gz" ./
