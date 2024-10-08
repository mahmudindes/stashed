package logger

import (
	"io"
	"time"

	"github.com/go-logr/logr"
	"github.com/go-logr/zerologr"
	"github.com/rs/zerolog"

	desuengine "github.com/mahmudindes/orenolink-desuengine"
)

type Logger struct {
	logger logr.Logger
}

func New(w io.Writer) Logger {
	zerolog.MessageFieldName = "msg"
	zerolog.TimestampFunc = func() time.Time { return time.Now().UTC() }
	zerolog.LevelFieldName = ""
	logBack := zerolog.New(w)
	logBack = logBack.With().Fields(map[string]any{"service": desuengine.Name}).Timestamp().Logger()

	zerologr.VerbosityFieldName = "verbosity"
	logSink := zerologr.NewLogSink(&logBack)

	logger := logr.New(logSink)

	return Logger{logger: logger}
}

func (l Logger) With(keysAndValues ...any) Logger {
	l.logger = l.logger.WithValues(keysAndValues...)
	return l
}

func (l Logger) WithName(name string) Logger {
	l.logger = l.logger.WithName(name)
	return l
}

func (l Logger) Message(msg string, args ...any) {
	l.logger.Info(msg, args...)
}

func (l Logger) ErrMessage(err error, msg string, args ...any) {
	l.logger.Error(err, msg, args...)
}
