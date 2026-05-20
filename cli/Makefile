BINARY := tm
INSTALL_DIR := $(HOME)/.local/bin

.PHONY: build install tidy clean

build: tidy
	go build -o $(BINARY) .

install: build
	mkdir -p $(INSTALL_DIR)
	cp $(BINARY) $(INSTALL_DIR)/$(BINARY)
	@echo "Installed to $(INSTALL_DIR)/$(BINARY)"
	@echo "Make sure $(INSTALL_DIR) is in your PATH."

tidy:
	go mod tidy

clean:
	rm -f $(BINARY)
