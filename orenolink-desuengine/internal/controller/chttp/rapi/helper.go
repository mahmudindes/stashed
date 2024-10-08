package rapi

import (
	"errors"
	"net/http"
	"net/url"
	"strings"

	"github.com/mahmudindes/orenolink-desuengine/internal/controller/chttp/utilb"
	"github.com/mahmudindes/orenolink-desuengine/internal/model"
	"github.com/mahmudindes/orenolink-desuengine/internal/utila"
)

func queryOrderBys(obs []string) model.OrderBys {
	var orderBys model.OrderBys
	for _, ob := range obs {
		obps := strings.Split(ob, " ")
		if len(obps) < 1 {
			continue
		}

		orderBy := model.OrderBy{Name: obps[0]}
		for _, obp := range obps[1:] {
			param, value, ok := strings.Cut(obp, "=")
			if !ok {
				continue
			}

			switch strings.ToLower(param) {
			case "sort":
				orderBy.Sort = value
			case "null":
				orderBy.Null = value
			}
		}

		orderBys = append(orderBys, orderBy)
	}
	return orderBys
}

func formDecode(form url.Values, v any) error {
	return utilb.FormDecoder.Decode(v, form)
}

func response(w http.ResponseWriter, v any, code int) {
	utilb.ResponseJSON(w, v, code)
}

func responseErr(w http.ResponseWriter, err string, status int) {
	response(w, Error{
		StatusCode: status,
		Message:    err,
	}, status)
}

func responseErr404(w http.ResponseWriter) {
	responseErr(w, "Not found.", http.StatusNotFound)
}

func responseErr500(w http.ResponseWriter) {
	responseErr(w, "Internal server error.", http.StatusInternalServerError)
}

func responseServiceErr(w http.ResponseWriter, err error) {
	switch {
	case errors.As(err, &model.ErrNotFound):
		responseErr404(w)
	case errors.As(err, &model.ErrGeneric):
		responseErr(w, utila.CapitalPeriod(err.Error()), http.StatusBadRequest)
	case errors.As(err, &model.ErrDatabase):
		responseErr(w, "Database has encountered a problem.", http.StatusInternalServerError)
	default:
		responseErr500(w)
	}
}
